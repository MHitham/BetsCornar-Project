<?php

namespace App\Services;

use App\Helpers\PhoneHelper;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceReturn;
use App\Models\InvoiceReturnItem;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InvoiceService
{
    public function __construct(
        private readonly StockService $stockService,
    ) {}

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

    public function saveQuickSale(array $data): Invoice
    {
        $invoice = DB::transaction(function () use ($data) {
            $customerId = null;
            $customerName = trim((string) ($data['customer_name'] ?? ''));
            if ($customerName === '') {
                $customerName = __('invoices.messages.walk_in_customer');
            }

            if (! empty($data['customer_id'])) {
                $customer = Customer::find((int) $data['customer_id']);
                if ($customer) {
                    $customerId = $customer->id;
                    $customerName = $customer->name;
                }
            } elseif (! empty($data['customer_phone'])) {

                $normalizedPhone = PhoneHelper::normalize($data['customer_phone']);
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

                if ($product->track_stock) {
                    if ($product->type === 'vaccination') {
                        $this->stockService->deductVaccineStockFefo($product, $qty, $invoiceItem);
                    } else {
                        $this->stockService->decreaseStock($product, $qty);
                    }
                }
            }

            $total = round($lineTotal, 2);
            $invoice->update([
                'total' => $total,
                'amount_paid' => $total,
            ]);

            if ($total > 0) {
                \App\Models\InvoicePayment::create([
                    'invoice_id' => $invoice->id,
                    'amount' => $total,
                    'notes' => 'تسديد تلقائي - بيع سريع',
                    'paid_at' => now(),
                ]);
            }

            return $invoice;
        });

        Cache::forget('notifications.alerts');

        return $invoice;
    }

    public function cancelInvoice(Invoice $invoice, ?string $reason = null): Invoice
    {

        if ($invoice->isCancelled()) {
            throw new RuntimeException(__('invoices.messages.already_cancelled'));
        }

        return DB::transaction(function () use ($invoice, $reason) {

            $invoice->load(['items.product', 'items.vaccineBatches']);

            foreach ($invoice->items as $item) {
                $product = $item->product;

                if (! $product || ! $product->track_stock) {
                    continue;
                }

                if ($product->type === 'vaccination') {

                    $this->stockService->restoreVaccineStock($item);
                } else {

                    $this->stockService->increaseStock($product, $item->quantity);
                }
            }

            $invoice->vaccinations()->delete();

            $invoice->update([
                'status' => 'cancelled',
                'cancellation_reason' => $reason,
                'cancelled_at' => now(),
            ]);

            Cache::forget('dashboard.total_vaccinations');

            Cache::forget(dashboardKey('upcoming_vaccinations'));

            Cache::forget(dashboardKey('batch_expiry'));

            return $invoice->fresh();
        });
    }

    public function createReturn(Invoice $invoice, array $items, ?string $reason = null): InvoiceReturn
    {
        if ($invoice->isCancelled()) {
            throw new \RuntimeException('لا يمكن إنشاء مرتجع لفاتورة ملغية.');
        }

        return DB::transaction(function () use ($invoice, $items, $reason) {
            $totalRefund = 0;

            $return = InvoiceReturn::create([
                'invoice_id' => $invoice->id,
                'reason' => $reason,
                'total_refund' => 0,
                'created_by' => auth()->id(),
            ]);

            foreach ($items as $row) {
                $qtyToReturn = round((float) $row['quantity_returned'], 2);
                if ($qtyToReturn <= 0) {
                    continue;
                }

                $invoiceItem = \App\Models\InvoiceItem::with('product')
                    ->lockForUpdate()
                    ->findOrFail((int) $row['invoice_item_id']);

                $maxReturnable = round((float) $invoiceItem->quantity, 2);

                if ($qtyToReturn > $maxReturnable) {
                    throw new \RuntimeException(
                        "الكمية المُرجعة ({$qtyToReturn}) تتجاوز الحد المسموح ({$maxReturnable}) للمنتج: {$invoiceItem->product->name}"
                    );
                }

                $lineTotal = round($qtyToReturn * (float) $invoiceItem->unit_price, 2);

                InvoiceReturnItem::create([
                    'invoice_return_id' => $return->id,
                    'invoice_item_id' => $invoiceItem->id,
                    'product_id' => $invoiceItem->product_id,
                    'quantity_returned' => $qtyToReturn,
                    'unit_price' => $invoiceItem->unit_price,
                    'line_total' => $lineTotal,
                ]);

                $totalRefund += $lineTotal;

                $product = $invoiceItem->product;
                if ($product && $product->track_stock) {
                    if ($product->type === 'vaccination') {
                        $this->stockService->restorePartialVaccineStock($invoiceItem, $qtyToReturn);
                    } else {
                        $this->stockService->increaseStock($product, $qtyToReturn);
                    }
                }

                $newQuantity = round((float) $invoiceItem->quantity - $qtyToReturn, 2);
                $newLineTotal = round($newQuantity * (float) $invoiceItem->unit_price, 2);

                $invoiceItem->update([
                    'quantity' => $newQuantity,
                    'line_total' => $newLineTotal,
                ]);
            }

            $return->update(['total_refund' => round($totalRefund, 2)]);

            $newInvoiceTotal = max(0, round((float) $invoice->total - $totalRefund, 2));
            $invoice->update(['total' => $newInvoiceTotal]);

            return $return;
        });
    }

    public function getDailyRevenueSummary(string $date, ?int $createdBy = null): array
    {
        $query = Invoice::query()->whereDate('created_at', $date);

        if ($createdBy !== null) {
            $query->where('created_by', $createdBy);
        }

        $confirmed = (clone $query)->confirmed()->get(['total', 'source']);

        return [
            'date' => $date,
            'label' => $date,
            'period_type' => 'day',
            'gross_revenue' => (float) $confirmed->sum('total'),
            'invoice_count' => $confirmed->count(),
            'cancelled_count' => (int) (clone $query)->cancelled()->count(),
            'customer_visits' => $confirmed->where('source', 'customer')->count(),
            'quick_sales' => $confirmed->where('source', '!=', 'customer')->count(),
        ];
    }

    public function getMonthlyRevenueSummary(int $year, int $month, ?int $createdBy = null): array
    {
        $query = Invoice::query()
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month);

        if ($createdBy !== null) {
            $query->where('created_by', $createdBy);
        }

        $confirmed = (clone $query)->confirmed()->get(['total', 'source']);

        return [
            'date' => sprintf('%04d-%02d', $year, $month),
            'label' => sprintf('%04d-%02d', $year, $month),
            'period_type' => 'month',
            'gross_revenue' => (float) $confirmed->sum('total'),
            'invoice_count' => $confirmed->count(),
            'cancelled_count' => (int) (clone $query)->cancelled()->count(),
            'customer_visits' => $confirmed->where('source', 'customer')->count(),
            'quick_sales' => $confirmed->where('source', '!=', 'customer')->count(),
        ];
    }
}
