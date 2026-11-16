<?php

use App\Models\Animal;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceItemVaccineBatch;
use App\Models\Product;
use App\Models\Vaccination;
use App\Models\VaccineBatch;
use App\Services\CustomerVisitService;
use App\Services\StockService;

beforeEach(function () {
    $this->visitService = app(CustomerVisitService::class);
    $this->stockService = app(StockService::class);

    // Consultation product required by CustomerVisitService when consultation_price > 0
    $this->consultationProduct = Product::factory()->service()->create([
        'name' => 'كشف',
        'price' => 150.0,
        'is_active' => true,
    ]);
});

test('saving a visit with empty vaccinations array creates invoice and consultation item but zero vaccination rows', function () {
    $visitData = [
        'name' => 'محمد أحمد',
        'phone' => '01011112222',
        'animal_type' => 'قط',
        'consultation_price' => 150.0,
        'vaccinations' => [],
    ];

    $invoice = $this->visitService->saveVisit($visitData);

    expect($invoice)->toBeInstanceOf(Invoice::class)
        ->and($invoice->status)->toBe('confirmed')
        ->and((float) $invoice->total)->toBe(150.0);

    expect($invoice->items)->toHaveCount(1)
        ->and($invoice->items->first()->product_id)->toBe($this->consultationProduct->id)
        ->and((float) $invoice->items->first()->line_total)->toBe(150.0);

    expect(Vaccination::where('invoice_id', $invoice->id)->count())->toBe(0);
});

test('saving a visit with multiple vaccinations creates one invoice_item and one vaccination row per entry linked to invoice', function () {
    $vaccine1 = Product::factory()->vaccination()->create(['name' => 'تطعيم سداسي', 'price' => 200]);
    $batch1 = VaccineBatch::factory()->create([
        'product_id' => $vaccine1->id,
        'expiry_date' => now()->addMonths(6)->toDateString(),
        'quantity_received' => 10,
        'quantity_remaining' => 10,
    ]);
    $this->stockService->recalculateVaccineStock($vaccine1);

    $vaccine2 = Product::factory()->vaccination()->create(['name' => 'تطعيم سعار', 'price' => 120]);
    $batch2 = VaccineBatch::factory()->create([
        'product_id' => $vaccine2->id,
        'expiry_date' => now()->addMonths(8)->toDateString(),
        'quantity_received' => 10,
        'quantity_remaining' => 10,
    ]);
    $this->stockService->recalculateVaccineStock($vaccine2);

    $visitData = [
        'name' => 'سارة علي',
        'phone' => '01022223333',
        'animal_type' => 'كلب',
        'consultation_price' => 150.0,
        'vaccinations' => [
            [
                'vaccine_product_id' => $vaccine1->id,
                'vaccine_quantity' => 1,
                'vaccine_unit_price' => 200.0,
                'vaccination_date' => now()->toDateString(),
                'next_dose_date' => now()->addDays(21)->toDateString(),
            ],
            [
                'vaccine_product_id' => $vaccine2->id,
                'vaccine_quantity' => 2,
                'vaccine_unit_price' => 120.0,
                'vaccination_date' => now()->toDateString(),
                'next_dose_date' => now()->addYear()->toDateString(),
            ],
        ],
    ];

    $invoice = $this->visitService->saveVisit($visitData);

    // Items: 1 consultation (150) + 1 vaccine1 (200) + 1 vaccine2 (2*120=240) = 590
    expect((float) $invoice->total)->toBe(590.0)
        ->and($invoice->items)->toHaveCount(3);

    $vaccineItems = $invoice->items()->whereIn('product_id', [$vaccine1->id, $vaccine2->id])->get();
    expect($vaccineItems)->toHaveCount(2);

    $vaccinations = Vaccination::where('invoice_id', $invoice->id)->get();
    expect($vaccinations)->toHaveCount(2)
        ->and($vaccinations->pluck('product_id')->all())->toEqualCanonicalizing([$vaccine1->id, $vaccine2->id])
        ->and($vaccinations->every(fn ($v) => $v->invoice_id === $invoice->id))->toBeTrue()
        ->and($vaccinations->every(fn ($v) => $v->customer_id === $invoice->customer_id))->toBeTrue();

    // Verify stock deductions
    expect((float) $batch1->fresh()->quantity_remaining)->toBe(9.0)
        ->and((float) $batch2->fresh()->quantity_remaining)->toBe(8.0);
});

test('if any step in the visit fails the entire transaction rolls back completely', function () {
    $vaccine1 = Product::factory()->vaccination()->create(['name' => 'لقاح 1', 'price' => 100]);
    $batch1 = VaccineBatch::factory()->create([
        'product_id' => $vaccine1->id,
        'expiry_date' => now()->addMonths(6)->toDateString(),
        'quantity_received' => 10,
        'quantity_remaining' => 10,
    ]);
    $this->stockService->recalculateVaccineStock($vaccine1);

    $vaccine2 = Product::factory()->vaccination()->create(['name' => 'لقاح 2', 'price' => 150]);
    $batch2 = VaccineBatch::factory()->create([
        'product_id' => $vaccine2->id,
        'expiry_date' => now()->addMonths(6)->toDateString(),
        'quantity_received' => 1,
        'quantity_remaining' => 1, // Only 1 available, but we will request 5
    ]);
    $this->stockService->recalculateVaccineStock($vaccine2);

    $targetPhone = '01055556666';
    $normalizedPhone = '201055556666';

    expect(Customer::where('phone', $normalizedPhone)->exists())->toBeFalse();

    $initialCustomerCount = Customer::count();
    $initialInvoiceCount = Invoice::count();
    $initialItemCount = InvoiceItem::count();
    $initialVaccinationCount = Vaccination::count();
    $initialPivotCount = InvoiceItemVaccineBatch::count();

    $visitData = [
        'name' => 'عميل تجريبي جديد',
        'phone' => $targetPhone,
        'animal_type' => 'قط',
        'consultation_price' => 150.0,
        'vaccinations' => [
            [
                'vaccine_product_id' => $vaccine1->id,
                'vaccine_quantity' => 2,
                'vaccine_unit_price' => 100.0,
                'vaccination_date' => now()->toDateString(),
            ],
            [
                'vaccine_product_id' => $vaccine2->id,
                'vaccine_quantity' => 5, // Insufficient stock!
                'vaccine_unit_price' => 150.0,
                'vaccination_date' => now()->toDateString(),
            ],
        ],
    ];

    expect(function () use ($visitData) {
        $this->visitService->saveVisit($visitData);
    })->toThrow(RuntimeException::class);

    // Verify complete rollback
    expect(Customer::where('phone', $normalizedPhone)->exists())->toBeFalse()
        ->and(Customer::count())->toBe($initialCustomerCount)
        ->and(Invoice::count())->toBe($initialInvoiceCount)
        ->and(InvoiceItem::count())->toBe($initialItemCount)
        ->and(Vaccination::count())->toBe($initialVaccinationCount)
        ->and(InvoiceItemVaccineBatch::count())->toBe($initialPivotCount)
        ->and((float) $batch1->fresh()->quantity_remaining)->toBe(10.0)
        ->and((float) $vaccine1->fresh()->quantity)->toBe(10.0)
        ->and((float) $batch2->fresh()->quantity_remaining)->toBe(1.0)
        ->and((float) $vaccine2->fresh()->quantity)->toBe(1.0);
});

test('when animal_id is provided it propagates correctly to both invoice and all vaccinations', function () {
    $normalizedPhone = '201077778888';
    $customer = Customer::factory()->create([
        'name' => 'طارق كمال',
        'phone' => $normalizedPhone,
    ]);

    $animal = Animal::factory()->create([
        'customer_id' => $customer->id,
        'name' => 'بوسي',
        'species' => 'cat',
    ]);

    $vaccine = Product::factory()->vaccination()->create();
    VaccineBatch::factory()->create([
        'product_id' => $vaccine->id,
        'expiry_date' => now()->addMonths(5)->toDateString(),
        'quantity_received' => 10,
        'quantity_remaining' => 10,
    ]);
    $this->stockService->recalculateVaccineStock($vaccine);

    $visitData = [
        'name' => $customer->name,
        'phone' => '01077778888',
        'animal_type' => 'cat',
        'animal_id' => $animal->id,
        'consultation_price' => 150.0,
        'vaccinations' => [
            [
                'vaccine_product_id' => $vaccine->id,
                'vaccine_quantity' => 1,
                'vaccine_unit_price' => 200.0,
                'vaccination_date' => now()->toDateString(),
            ],
        ],
    ];

    $invoice = $this->visitService->saveVisit($visitData);

    expect($invoice->animal_id)->toBe($animal->id);

    $vaccinations = Vaccination::where('invoice_id', $invoice->id)->get();
    expect($vaccinations)->toHaveCount(1)
        ->and($vaccinations->first()->animal_id)->toBe($animal->id)
        ->and($vaccinations->first()->customer_id)->toBe($customer->id);
});
