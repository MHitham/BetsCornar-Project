<?php

namespace App\Services;

use App\Helpers\PhoneHelper;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
// تم الإضافة: استخدام الكاش لمسح مؤشرات لوحة التحكم بعد حفظ الزيارة
use App\Models\Vaccination;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CustomerVisitService
{
    public function __construct(
        private readonly StockService $stockService,
        private readonly InvoiceService $invoiceService,
    ) {}

    /**
     * Finds an existing customer by normalized phone or creates a new one.
     */
    public function findOrCreateCustomer(string $normalizedPhone, array $attributes = []): Customer
    {
        return Customer::findOrCreateByPhone($normalizedPhone, $attributes);
    }

    /**
     * Save a full customer visit within a DB transaction.
     *
     * Expected $data keys:
     *   name, phone, address?, animal_type, notes?,
     *   consultation_price,
     *   has_vaccination (bool),
     *   vaccine_product_id?, vaccine_quantity?, vaccination_date?, next_dose_date?,
     *   additional_items? => [['product_id', 'quantity', 'unit_price'], ...]
     *
     * @throws RuntimeException on insufficient vaccine stock
     */
    public function saveVisit(array $data): Invoice
    {
        return DB::transaction(function () use ($data) {
            // 1. Normalize phone and find/create customer
            // استخدام PhoneHelper المركزي لتوحيد أرقام الهاتف
            $normalizedPhone = PhoneHelper::normalize($data['phone'] ?? '');
            $customer = $this->findOrCreateCustomer($normalizedPhone, [
                'name' => $data['name'],
                'address' => $data['address'] ?? null,
                'animal_type' => $data['animal_type'],
                'notes' => $data['notes'] ?? null,
            ]);

            // Validate animal ownership or create a new animal inline
            $animalId = null;
            if (! empty($data['animal_id'])) {
                if ($data['animal_id'] === 'new_animal' && ! empty($data['new_animal'])) {
                    $animal = $customer->animals()->create($data['new_animal']);
                    $animalId = $animal->id;
                } else {
                    $animal = \App\Models\Animal::find($data['animal_id']);
                    if (! $animal || $animal->customer_id !== $customer->id) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'animal_id' => ['الحيوان المحدد غير مسجل لهذا العميل.'],
                        ]);
                    }
                    $animalId = $animal->id;
                }
            }

            // 2. Create invoice
            $invoice = Invoice::create([
                'invoice_number' => $this->invoiceService->generateInvoiceNumber(),
                'customer_id' => $customer->id,
                'animal_id' => $animalId,
                'customer_name' => $customer->name,
                'source' => 'customer',
                'diagnosis' => $data['diagnosis'] ?? null,
                'total' => 0,
                'status' => 'confirmed',
                // تسجيل المبلغ المدفوع فوراً لو كان دفع جزئي
                'amount_paid' => round((float) ($data['amount_paid'] ?? 0), 2),
                // تم الإضافة: تتبع المستخدم الذي أنشأ الفاتورة
                'created_by' => auth()->id(),
            ]);

            $lineTotal = 0.0;

            // 3. Consultation invoice item
            $consultationPrice = round((float) ($data['consultation_price'] ?? 0), 2);
            if ($consultationPrice > 0) {
                $consultationProduct = Product::query()
                    ->where('type', '=', 'service')
                    ->active()
                    ->orderByDesc('id')
                    ->first();

                if ($consultationProduct) {
                    $consultItem = $invoice->items()->create([
                        'product_id' => $consultationProduct->id,
                        'quantity' => 1,
                        'unit_price' => $consultationPrice,
                        'line_total' => $consultationPrice,
                    ]);
                    $lineTotal += $consultationPrice;
                    // Services don't track stock, so no deduction needed
                }
            }

            // 4. التطعيمات المتعددة ─────────────────────────────────────
            foreach ($data['vaccinations'] ?? [] as $vaccinationData) {
                $vaccineProduct = Product::lockForUpdate()->findOrFail((int) $vaccinationData['vaccine_product_id']);
                $vaccineQty = round((float) ($vaccinationData['vaccine_quantity'] ?? 1), 2);
                $vaccinePrice = round((float) ($vaccinationData['vaccine_unit_price'] ?? $vaccineProduct->price), 2);
                $vaccineLineTotal = round($vaccineQty * $vaccinePrice, 2);

                // إنشاء بند الفاتورة للتطعيم
                $vaccineItem = $invoice->items()->create([
                    'product_id' => $vaccineProduct->id,
                    'quantity' => $vaccineQty,
                    'unit_price' => $vaccinePrice,
                    'line_total' => $vaccineLineTotal,
                ]);
                $lineTotal += $vaccineLineTotal;

                // خصم ستوك التطعيم عبر وحدات FEFO (يرمي استثناء إذا كان الستوك غير كافي)
                $this->stockService->deductVaccineStockFefo($vaccineProduct, $vaccineQty, $vaccineItem);

                // إنشاء سجل التطعيم
                Vaccination::create([
                    'customer_id' => $customer->id,
                    'animal_id' => $animalId,
                    'product_id' => $vaccineProduct->id,
                    'invoice_id' => $invoice->id,
                    'vaccination_date' => $vaccinationData['vaccination_date'] ?? now()->toDateString(),
                    'next_dose_date' => ! empty($vaccinationData['next_dose_date']) ? $vaccinationData['next_dose_date'] : null,
                ]);
            }

            // 6. Additional products/services
            foreach ($data['additional_items'] ?? [] as $item) {
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

                // Deduct stock
                if ($product->track_stock) {
                    if ($product->type === 'vaccination') {
                        $this->stockService->deductVaccineStockFefo($product, $qty, $invoiceItem);
                    } else {
                        $this->stockService->decreaseStock($product, $qty);
                    }
                }
            }

            // 7. Update invoice total
            $invoice->update(['total' => round($lineTotal, 2)]);

            // تم الإضافة: تسجيل الدفعة الأولى في سجل الدفعات إذا كان هناك مبلغ مدفوع
            if ((float) $invoice->amount_paid > 0) {
                \App\Models\InvoicePayment::create([
                    'invoice_id' => $invoice->id,
                    'amount'     => (float) $invoice->amount_paid,
                    'notes'      => 'دفعة مقدمة عند إنشاء الفاتورة',
                    'paid_at'    => now(),
                ]);
            }

            // تم الإضافة: تحديث كاش إجمالي التطعيمات بعد إنشاء زيارة قد تحتوي على تطعيمات جديدة
            Cache::forget('dashboard.total_vaccinations');
            // تم الإضافة: تحديث كاش التطعيمات القادمة بعد إنشاء سجل تطعيم جديد أو تعديل مواعيده
            Cache::forget(dashboardKey('upcoming_vaccinations'));

            return $invoice;
        });
    }
}
