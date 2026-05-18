<?php

use App\Models\Animal;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Vaccination;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('orders a customer animals relationship by newest first', function () {
    $customer = Customer::factory()->create();

    $olderAnimal = Animal::factory()->for($customer)->create([
        'created_at' => now()->subDay(),
    ]);
    $newerAnimal = Animal::factory()->for($customer)->create([
        'created_at' => now(),
    ]);

    expect($customer->fresh()->animals->modelKeys())->toBe([
        $newerAnimal->id,
        $olderAnimal->id,
    ]);
});

it('casts animal attributes and scopes active animals', function () {
    $activeAnimal = Animal::factory()->create([
        'weight' => 4.5,
        'is_active' => true,
    ]);
    Animal::factory()->create([
        'is_active' => false,
    ]);

    expect($activeAnimal->fresh()->is_active)->toBeTrue()
        ->and($activeAnimal->fresh()->weight)->toBe('4.50')
        ->and(Animal::query()->active()->pluck('id')->all())->toBe([$activeAnimal->id]);
});

it('links invoices and vaccinations to an animal', function () {
    $customer = Customer::factory()->create();
    $animal = Animal::factory()->for($customer)->create();
    $invoice = Invoice::factory()->create([
        'customer_id' => $customer->id,
        'animal_id' => $animal->id,
        'customer_name' => $customer->name,
        'source' => 'customer',
    ]);
    $product = Product::factory()->vaccination()->create();
    $vaccination = Vaccination::factory()->create([
        'customer_id' => $customer->id,
        'animal_id' => $animal->id,
        'product_id' => $product->id,
        'invoice_id' => $invoice->id,
    ]);

    expect($invoice->animal->is($animal))->toBeTrue()
        ->and($vaccination->animal->is($animal))->toBeTrue()
        ->and($animal->fresh()->invoices->modelKeys())->toBe([$invoice->id])
        ->and($animal->fresh()->vaccinations->modelKeys())->toBe([$vaccination->id]);
});
