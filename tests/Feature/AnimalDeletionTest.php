<?php

use App\Models\Animal;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Vaccination;
use Illuminate\Support\Facades\Cache;

it('deletes a pet file, preserves invoices, and removes vaccinations', function () {
    $this->actingAsAdmin();
    $customer = Customer::factory()->create();
    $animal = Animal::factory()->create(['customer_id' => $customer->id, 'name' => 'محذوف']);
    $invoice = Invoice::factory()->create([
        'customer_id' => $customer->id,
        'animal_id' => $animal->id,
        'animal_name' => null,
        'customer_name' => $customer->name,
    ]);
    $vaccine = Product::factory()->vaccination()->create();
    $vaccination = Vaccination::factory()->create([
        'customer_id' => $customer->id,
        'animal_id' => $animal->id,
        'invoice_id' => $invoice->id,
        'product_id' => $vaccine->id,
    ]);
    Cache::put(dashboardKey('upcoming_vaccinations'), 'cached');
    Cache::put('dashboard.total_vaccinations', 5);

    $this->delete(route('animals.destroy', $animal))->assertRedirect(route('customers.show', $customer));

    expect(Animal::whereKey($animal->id)->exists())->toBeFalse()
        ->and(Vaccination::whereKey($vaccination->id)->exists())->toBeFalse()
        ->and($invoice->fresh()->animal_id)->toBeNull()
        ->and($invoice->fresh()->animal_name)->toBe('محذوف')
        ->and(Cache::get(dashboardKey('upcoming_vaccinations')))->toBeNull()
        ->and(Cache::get('dashboard.total_vaccinations'))->toBeNull();

    $response = $this->get(route('customers.show', $customer))->assertOk();
    preg_match('/<details id="medical-record-archive"[^>]*>(.*?)<\/details>/s', $response->getContent(), $archive);
    expect($archive)->not->toBeEmpty();
    expect($archive[0])->not->toMatch('/<details[^>]*\sopen[\s=>]/');
    expect($archive[1])->toContain('أرشيف', 'محذوف', $invoice->invoice_number, route('invoices.show', $invoice));
});

it('does not allow an employee to delete a pet file', function () {
    $this->actingAsEmployee();
    $animal = Animal::factory()->create();

    $this->delete(route('animals.destroy', $animal))->assertForbidden();
    expect(Animal::whereKey($animal->id)->exists())->toBeTrue();
});
