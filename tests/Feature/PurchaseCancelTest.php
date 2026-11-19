<?php

use App\Models\Product;
use App\Models\Supplier;
use App\Services\PurchaseService;

it('cancels a normal purchase and restores quantity and average cost', function () {
    $this->actingAsAdmin();
    $product = Product::factory()->product()->create(['quantity' => 10, 'average_cost' => 20]);
    $supplier = Supplier::create(['name' => 'Cancel supplier', 'is_active' => true]);
    $order = app(PurchaseService::class)->createPurchaseOrder([
        'supplier_id' => $supplier->id, 'purchased_at' => today()->toDateString(),
        'items' => [['product_id' => $product->id, 'quantity' => 5, 'purchase_price_per_unit' => 20, 'selling_price_per_unit' => 40]],
    ]);

    app(PurchaseService::class)->cancelPurchase($order);
    expect((float) $product->fresh()->quantity)->toBe(10.0)
        ->and((float) $product->fresh()->average_cost)->toBe(20.0)
        ->and($order->fresh()->status)->toBe('cancelled');
});

it('rejects a second cancellation without changing the order', function () {
    $this->actingAsAdmin();
    $product = Product::factory()->product()->create(['quantity' => 1]);
    $order = app(PurchaseService::class)->createPurchaseOrder(['purchased_at' => today()->toDateString(), 'items' => [['product_id' => $product->id, 'quantity' => 1, 'purchase_price_per_unit' => 5, 'selling_price_per_unit' => 10]]]);
    $service = app(PurchaseService::class);
    $service->cancelPurchase($order);
    expect(fn () => $service->cancelPurchase($order->fresh()))->toThrow(DomainException::class);
});

test('cancelled purchase is excluded from supplier balance', function () {
    $this->actingAsAdmin();
    $supplier = Supplier::create(['name' => 'Debt supplier', 'is_active' => true]);
    $product = Product::factory()->product()->create(['quantity' => 0]);
    $order = app(PurchaseService::class)->createPurchaseOrder(['supplier_id' => $supplier->id, 'purchased_at' => today()->toDateString(), 'items' => [['product_id' => $product->id, 'quantity' => 2, 'purchase_price_per_unit' => 5, 'selling_price_per_unit' => 10]]]);
    app(PurchaseService::class)->cancelPurchase($order);
    expect($supplier->fresh()->balance)->toBe(0.0);
});
