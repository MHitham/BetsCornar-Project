<?php

use App\Models\Animal;
use App\Models\Customer;
use App\Models\Product;
use App\Services\CustomerVisitService;

it('saves treatment on the visit invoice and shows it in pet and owner files', function () {
    $this->actingAsAdmin();
    $customer = Customer::factory()->create();
    $animal = Animal::factory()->for($customer)->create();
    $consultation = Product::factory()->service()->create(['name' => 'كشف']);
    $invoice = app(CustomerVisitService::class)->saveVisit([
        'name' => $customer->name, 'phone' => $customer->phone, 'animal_type' => 'cat', 'animal_id' => $animal->id,
        'consultation_price' => 100, 'diagnosis' => 'تشخيص', 'treatment' => 'دواء مرتين يومياً',
    ]);

    expect($invoice->fresh()->treatment)->toBe('دواء مرتين يومياً');
    $this->get(route('animals.show', $animal))->assertSuccessful()->assertSee('دواء مرتين يومياً');
    $this->get(route('customers.show', $customer))->assertSuccessful()->assertSee('دواء مرتين يومياً');
});

test('whitespace-only treatment is stored as null and empty treatment is accepted', function () {
    $this->actingAsAdmin();
    $customer = Customer::factory()->create();
    $serviceProduct = Product::factory()->service()->create(['name' => 'كشف']);
    $invoice = app(CustomerVisitService::class)->saveVisit([
        'name' => $customer->name, 'phone' => $customer->phone, 'animal_type' => 'cat',
        'consultation_price' => 50, 'treatment' => '   ',
    ]);
    expect($invoice->fresh()->treatment)->toBeNull();
});
