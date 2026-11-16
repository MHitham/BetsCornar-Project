<?php

namespace App\Services;

use App\Helpers\PhoneHelper;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Vaccination;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CustomerVisitService
{
    public function __construct(
        private readonly StockService $stockService,
        private readonly InvoiceService $invoiceService,
    ) {}

    /**
     * Normalize a phone number to a standard format (e.g. 201012345678).
     */
    public function normalizePhone(string $phone): string
    {
        return PhoneHelper::normalize($phone);
    }

    public function findOrCreateCustomer(string $normalizedPhone, array $attributes = []): Customer
    {
        return Customer::findOrCreateByPhone($normalizedPhone, $attributes);
    }

    public function saveVisit(array $data): Invoice
    {
        return DB::transaction(function () use ($data) {

            $normalizedPhone = PhoneHelper::normalize($data['phone'] ?? '');
            $customer = $this->findOrCreateCustomer($normalizedPhone, [
                'name' => $data['name'],
                'address' => $data['address'] ?? null,
                'animal_type' => $data['animal_type'],
                'notes' => $data['notes'] ?? null,
            ]);

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

            $invoice = Invoice::create([
                'invoice_number' => $this->invoiceService->generateInvoiceNumber(),
                'customer_id' => $customer->id,
                'animal_id' => $animalId,
                'customer_name' => $customer->name,
                'source' => 'customer',
                'diagnosis' => $data['diagnosis'] ?? null,
                'total' => 0,
                'status' => 'confirmed',

                'amount_paid' => round((float) ($data['amount_paid'] ?? 0), 2),

                'created_by' => auth()->id(),
            ]);

            $lineTotal = 0.0;

            $consultationPrice = round((float) ($data['consultation_price'] ?? 0), 2);
            if ($consultationPrice > 0) {
                // البحث عن خدمة "كشف" بالاسم بالتحديد بدل آخر خدمة مضافة (تصليح الخلط في المنتج الافتراضي)
                $consultationProduct = Product::query()
                    ->active()
                    ->where('type', 'service')
                    ->whereRaw("TRIM(name) = ?", ['كشف'])
                    ->first();

                // التحقق من وجود منتج الكشف وإيقاف الحفظ في حالة عدم وجوده
                if (! $consultationProduct) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'consultation_price' => [__('customers.messages.select_consultation')],
                    ]);
                }

                if ($consultationProduct) {
                    $consultItem = $invoice->items()->create([
                        'product_id' => $consultationProduct->id,
                        'quantity' => 1,
                        'unit_price' => $consultationPrice,
                        'line_total' => $consultationPrice,
                    ]);
                    $lineTotal += $consultationPrice;

                }
            }

            foreach ($data['vaccinations'] ?? [] as $vaccinationData) {
                $vaccineProduct = Product::lockForUpdate()->findOrFail((int) $vaccinationData['vaccine_product_id']);
                $vaccineQty = round((float) ($vaccinationData['vaccine_quantity'] ?? 1), 2);
                $vaccinePrice = round((float) ($vaccinationData['vaccine_unit_price'] ?? $vaccineProduct->price), 2);
                $vaccineLineTotal = round($vaccineQty * $vaccinePrice, 2);

                $vaccineItem = $invoice->items()->create([
                    'product_id' => $vaccineProduct->id,
                    'quantity' => $vaccineQty,
                    'unit_price' => $vaccinePrice,
                    'line_total' => $vaccineLineTotal,
                ]);
                $lineTotal += $vaccineLineTotal;

                $this->stockService->deductVaccineStockFefo($vaccineProduct, $vaccineQty, $vaccineItem);

                Vaccination::create([
                    'customer_id' => $customer->id,
                    'animal_id' => $animalId,
                    'product_id' => $vaccineProduct->id,
                    'invoice_id' => $invoice->id,
                    'vaccination_date' => $vaccinationData['vaccination_date'] ?? now()->toDateString(),
                    'next_dose_date' => ! empty($vaccinationData['next_dose_date']) ? $vaccinationData['next_dose_date'] : null,
                ]);
            }

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

                if ($product->track_stock) {
                    if ($product->type === 'vaccination') {
                        $this->stockService->deductVaccineStockFefo($product, $qty, $invoiceItem);
                    } else {
                        $this->stockService->decreaseStock($product, $qty);
                    }
                }
            }

            $invoice->update(['total' => round($lineTotal, 2)]);

            if ((float) $invoice->amount_paid > 0) {
                \App\Models\InvoicePayment::create([
                    'invoice_id' => $invoice->id,
                    'amount' => (float) $invoice->amount_paid,
                    'notes' => 'دفعة مقدمة عند إنشاء الفاتورة',
                    'paid_at' => now(),
                ]);
            }

            Cache::forget('dashboard.total_vaccinations');

            Cache::forget(dashboardKey('upcoming_vaccinations'));

            Cache::forget('notifications.alerts');

            return $invoice;
        });
    }
}
