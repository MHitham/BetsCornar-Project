<?php

use App\Models\Animal;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Vaccination;
use Illuminate\Support\Facades\DB;

function makeGroupedCustomer(int $animals): Customer
{
    $customer = Customer::factory()->create();
    for ($index = 1; $index <= $animals; $index++) {
        $animal = Animal::factory()->create([
            'customer_id' => $customer->id,
            'name' => "Pet {$index}",
            'species' => 'cat',
        ]);
        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'animal_id' => $animal->id,
            'customer_name' => $customer->name,
            'invoice_number' => "GROUP-{$animals}-{$index}",
            'diagnosis' => "Diagnosis {$index}",
        ]);
        $vaccine = Product::factory()->vaccination()->create(['name' => "Vaccine {$index}"]);
        Vaccination::create([
            'customer_id' => $customer->id,
            'animal_id' => $animal->id,
            'product_id' => $vaccine->id,
            'invoice_id' => $invoice->id,
            'vaccination_date' => today(),
            'next_dose_date' => today()->addDays(30),
        ]);
    }

    return $customer;
}

test('customer file groups each invoice and vaccination under its own animal', function () {
    $this->actingAsAdmin();
    $customer = makeGroupedCustomer(2);

    $response = $this->get(route('customers.show', $customer));

    $response->assertSuccessful()
        ->assertSee('data-pet-name="Pet 1"', false)
        ->assertSee('data-pet-name="Pet 2"', false)
        ->assertSee('GROUP-2-1')
        ->assertSee('GROUP-2-2')
        ->assertSee('Vaccine 1')
        ->assertSee('Vaccine 2');

    $orphan = Invoice::factory()->create([
        'customer_id' => $customer->id,
        'animal_id' => null,
        'customer_name' => $customer->name,
        'invoice_number' => 'GROUP-ORPHAN',
    ]);
    $orphanResponse = $this->get(route('customers.show', $customer));
    $orphanResponse->assertSee('أرشيف')->assertSee('GROUP-ORPHAN')
        ->assertDontSeeText('سجلات بدون حيوان')
        ->assertDontSeeText('الأصناف');
    expect($orphan->fresh()->animal_id)->toBeNull();
});

test('customer file query count does not grow with the number of animals', function () {
    $this->actingAsAdmin();
    $one = makeGroupedCustomer(1);
    $this->get(route('customers.show', $one));
    DB::enableQueryLog();
    DB::flushQueryLog();
    $this->get(route('customers.show', $one));
    $oneQueries = count(DB::getQueryLog());
    DB::disableQueryLog();

    $four = makeGroupedCustomer(4);
    $this->get(route('customers.show', $four));
    DB::enableQueryLog();
    DB::flushQueryLog();
    $this->get(route('customers.show', $four));
    $fourQueries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($fourQueries)->toBe($oneQueries);
});
