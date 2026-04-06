<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
// تم الإضافة: استخدام الكاش لمسح مؤشرات لوحة التحكم بعد إلغاء الفاتورة
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InvoiceService
{
    public function __construct(
        private readonly StockService $stockService,
    ) {}

    /**
     * Generate a sequential, unique invoice number like INV-000001.
     * Uses a DB-level lock to prevent race conditions.
     */
    public function generateInvoiceNumber(): string
    {
        $last = Invoice::query()
            ->lockForUpdate()
            ->orderByDesc('id')
            ->value('invoice_number');

        if ($last && preg_match('/INV-(\d+)/', $last, $m)) {
            $next = (int) $m[1] + 1;
        } else {
            $next = 1;
        }

        return 'INV-'.str_pad($next, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Save a quick-sale invoice within a DB transaction.
     *
     * Expected $data keys:
     *   customer_name (string),
     *   customer_phone? (string, optional),
     *   items => [['product_id', 'quantity', 'unit_price'], ...]
     *
     * @throws RuntimeException on insufficient vaccine stock
     */
    public function saveQuickSale(array $data): Invoice
    {
        return DB::transaction(function () use ($data) {
            $customerId = null;
            $customerName = trim((string) ($data['customer_name'] ?? ''));
            if ($customerName === '') {
                $customerName = __('invoices.messages.walk_in_customer');
            }

            // تم التعديل: ربط العميل — الأولوية لـ customer_id المُختار من البحث المباشر
            if (! empty($data['customer_id'])) {
                $customer = Customer::find((int) $data['customer_id']);
                if ($customer) {
                    $customerId   = $customer->id;
                    $customerName = $customer->name;
                }
            } elseif (! empty($data['customer_phone'])) {
                // fallback قديم: ربط عن طريق رقم الهاتف
                $normalizedPhone = $this->normalizePhone($data['customer_phone']);
                $customer = Customer::where('phone', '=', $normalizedPhone)->first(['*']);
                if ($customer) {
                    $customerId = $customer->id;
                }
            }

            $invoice = Invoice::create([
                'invoice_number' => $this->generateInvoiceNumber(),
                'customer_id' => $customerId,
                'customer_name' => $customerName,
                'source' => 'quick_sale',
                'total' => 0,
                'status' => 'confirmed',
                // تم الإضافة: تتبع المستخدم الذي أنشأ الفاتورة
                'created_by' => auth()->id(),
            ]);

            $lineTotal = 0.0;

            foreach ($data['items'] as $item) {
                $product = Product::lockForUpdate()->findOrFail((int) $item['product_id']);
                $qty = round((float) $item['quantity'], 2);
                $price = round((float) $item['unit_price'], 2);
                $total = round($qty * $price, 2);

                $invoiceItem = $invoice->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'line_total' => $total,
                ]);

                $lineTotal += $total;

                // Deduct stock — vaccines use FEFO, no vaccination record created
                if ($product->track_stock) {
                    if ($product->type === 'vaccination') {
                        $this->stockService->deductVaccineStockFefo($product, $qty, $invoiceItem);
                    } else {
                        $this->stockService->decreaseStock($product, $qty);
                    }
                }
            }

            $invoice->update(['total' => round($lineTotal, 2)]);

            return $invoice;
        });
    }

    /**
     * إلغاء فاتورة مع إرجاع الستوك كاملاً في transaction واحدة.
     * يرفض الإلغاء إذا كانت الفاتورة ملغية بالفعل.
     */
    public function cancelInvoice(Invoice $invoice, ?string $reason = null): Invoice
    {
        // منع إلغاء فاتورة ملغية مسبقاً
        if ($invoice->isCancelled()) {
            throw new RuntimeException(__('invoices.messages.already_cancelled'));
        }

        return DB::transaction(function () use ($invoice, $reason) {
            // تحميل البنود مع المنتجات والـ vaccine batches
            $invoice->load(['items.product', 'items.vaccineBatches']);

            foreach ($invoice->items as $item) {
                $product = $item->product;

                if (! $product || ! $product->track_stock) {
                    continue;
                }

                if ($product->type === 'vaccination') {
                    // إرجاع ستوك التطعيم عبر الـ batches
                    $this->stockService->restoreVaccineStock($item);
                } else {
                    // إرجاع ستوك المنتج العادي
                    $this->stockService->increaseStock($product, $item->quantity);
                }
            }

            // تحديث حالة الفاتورة إلى ملغية مع تسجيل السبب والوقت
            $invoice->update([
                'status'              => 'cancelled',
                'cancellation_reason' => $reason,
                'cancelled_at'        => now(),
            ]);

            // تم الإضافة: تحديث كاش إجمالي التطعيمات لأن إلغاء الفاتورة قد يعيد عناصر تطعيم للمخزون
            Cache::forget('dashboard.total_vaccinations');
            // تم الإضافة: تحديث كاش التطعيمات القادمة بعد أي إلغاء قد يؤثر على مؤشرات اللوحة
            Cache::forget(dashboardKey('upcoming_vaccinations'));
            // تم الإضافة: تحديث كاش صلاحية التشغيلات لأن الإلغاء قد يعيد كميات إلى التشغيلات
            Cache::forget(dashboardKey('batch_expiry'));

            return $invoice->fresh();
        });
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        $digits = ltrim($digits, '0');
        if (! str_starts_with($digits, '20')) {
            $digits = '20'.$digits;
        }

        return $digits;
    }
}
