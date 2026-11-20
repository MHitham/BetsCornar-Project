<?php

use App\Models\Product;
use App\Models\PurchaseOrder;

it('receives a new product and vaccine in one purchase and makes them available for sale', function () {
    $this->actingAsAdmin();
    $expiry = today()->addYear();

    $response = $this->postWithToken(route('purchases.store'), [
        'purchased_at' => today()->toDateString(),
        'items' => [
            [
                'product_id' => 'new', 'new_product_name' => 'صنف اختبار', 'new_product_type' => 'product',
                'quantity' => 10, 'purchase_price_per_unit' => 20, 'selling_price_per_unit' => 35,
            ],
            [
                'product_id' => 'new', 'new_product_name' => 'لقاح اختبار', 'new_product_type' => 'vaccination',
                'quantity' => 4, 'purchase_price_per_unit' => 8, 'selling_price_per_unit' => 15,
                'expiry_date' => $expiry->toDateString(),
            ],
        ],
    ]);

    $order = PurchaseOrder::with('items.product', 'items.batch')->sole();
    $response->assertRedirect(route('purchases.show', $order));
    $product = $order->items->firstWhere('product.type', 'product')->product;
    $vaccineItem = $order->items->firstWhere('product.type', 'vaccination');
    $vaccine = $vaccineItem->product;
    $batch = $vaccineItem->batch;

    expect((float) $order->total_cost)->toBe(232.0)
        ->and($product->name)->toBe('صنف اختبار')
        ->and($product->is_active)->toBeTrue()
        ->and((float) $product->price)->toBe(35.0)
        ->and((float) $product->average_cost)->toBe(20.0)
        ->and((float) $product->quantity)->toBe(10.0)
        ->and($vaccine->name)->toBe('لقاح اختبار')
        ->and($vaccine->is_active)->toBeTrue()
        ->and((float) $vaccine->price)->toBe(15.0)
        ->and((float) $vaccine->quantity)->toBe(4.0)
        ->and((float) $batch->purchase_price)->toBe(8.0)
        ->and((float) $batch->selling_price)->toBe(15.0)
        ->and((float) $batch->quantity_remaining)->toBe(4.0)
        ->and($batch->expiry_date->toDateString())->toBe($expiry->toDateString());

    $this->get(route('invoices.create'))->assertSuccessful()
        ->assertViewHas('products', fn ($products) => $products->contains('id', $product->id)
            && $products->contains('id', $vaccine->id));

    $this->postWithToken(route('invoices.store'), [
        'items' => [
            ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 35],
            ['product_id' => $vaccine->id, 'quantity' => 1, 'unit_price' => 15],
        ],
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect((float) $product->fresh()->quantity)->toBe(9.0)
        ->and((float) $vaccine->fresh()->quantity)->toBe(3.0)
        ->and((float) $batch->fresh()->quantity_remaining)->toBe(3.0);
});

it('rejects a duplicate name and type and lets the operator select the existing out-of-stock product', function () {
    $this->actingAsAdmin();
    $existing = Product::factory()->product()->create(['name' => 'Test Existing', 'quantity' => 0, 'price' => 32]);
    $line = ['quantity' => 3, 'purchase_price_per_unit' => 20, 'selling_price_per_unit' => 40];
    $orderData = [
        '_submit_token' => 'new-product-rollback-and-retry',
        'purchased_at' => today()->toDateString(),
    ];

    $this->from(route('purchases.create'))->post(route('purchases.store'), $orderData + [
        'update_selling_price' => [0 => true],
        'items' => [
            $line + ['product_id' => $existing->id],
            $line + ['product_id' => 'new', 'new_product_name' => 'Temporary item', 'new_product_type' => 'product'],
            $line + ['product_id' => 'new', 'new_product_name' => ' test existing ', 'new_product_type' => 'product'],
        ],
    ])->assertRedirect(route('purchases.create'))
        ->assertSessionHasErrors('items.2.new_product_name')
        ->assertSessionHasInput('items.2.product_id', 'new');

    expect(PurchaseOrder::count())->toBe(0)
        ->and(Product::count())->toBe(1)
        ->and((float) $existing->fresh()->quantity)->toBe(0.0)
        ->and((float) $existing->fresh()->price)->toBe(32.0);

    $this->post(route('purchases.store'), $orderData + [
        'update_selling_price' => [0 => true],
        'items' => [$line + ['product_id' => $existing->id]],
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect(Product::count())->toBe(1)
        ->and(PurchaseOrder::sole()->items()->sole()->product_id)->toBe($existing->id)
        ->and((float) $existing->fresh()->quantity)->toBe(3.0)
        ->and((float) $existing->fresh()->price)->toBe(40.0);
});

it('validates the new item before creating a purchase', function (array $newProduct, string $error) {
    $this->actingAsAdmin();

    $this->postWithToken(route('purchases.store'), [
        'purchased_at' => today()->toDateString(),
        'items' => [$newProduct + [
            'product_id' => 'new', 'quantity' => 2, 'purchase_price_per_unit' => 10, 'selling_price_per_unit' => 15,
        ]],
    ])->assertSessionHasErrors('items.0.'.$error);

    expect(PurchaseOrder::count())->toBe(0)
        ->and(Product::count())->toBe(0);
})->with([
    'missing name' => [['new_product_type' => 'product'], 'new_product_name'],
    'unsupported type' => [['new_product_name' => 'Test item', 'new_product_type' => 'service'], 'new_product_type'],
    'vaccine without expiry' => [['new_product_name' => 'Test vaccine', 'new_product_type' => 'vaccination'], 'expiry_date'],
]);
