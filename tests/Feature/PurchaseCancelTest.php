<?php

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceItemVaccineBatch;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\VaccineBatch;
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

// اختبار تجميع كل سطور المنتج نفسه قبل مقارنة الكمية الحالية.
it('validates duplicate product lines cumulatively against current stock', function () {
    $this->actingAsAdmin();
    $product = Product::factory()->product()->create(['quantity' => 10]);
    $order = app(PurchaseService::class)->createPurchaseOrder([
        'purchased_at' => today()->toDateString(),
        'items' => [
            ['product_id' => $product->id, 'quantity' => 6, 'purchase_price_per_unit' => 10, 'selling_price_per_unit' => 20],
            ['product_id' => $product->id, 'quantity' => 5, 'purchase_price_per_unit' => 10, 'selling_price_per_unit' => 20],
        ],
    ]);
    $product->refresh()->update(['quantity' => 10]);

    expect(fn () => app(PurchaseService::class)->cancelPurchase($order->fresh()))->toThrow(DomainException::class);
    expect((float) $product->fresh()->quantity)->toBe(10.0)
        ->and($order->fresh()->status)->toBe('confirmed');
});

// اختبار منع إلغاء دفعة لقاح استُهلك جزء منها مع بقاء كل البيانات كما هي.
it('blocks cancellation when a vaccine batch is partly sold', function () {
    $this->actingAsAdmin();
    $product = Product::factory()->vaccination()->create();
    $order = app(PurchaseService::class)->createPurchaseOrder([
        'purchased_at' => today()->toDateString(),
        'items' => [['product_id' => $product->id, 'quantity' => 10, 'purchase_price_per_unit' => 5, 'selling_price_per_unit' => 10, 'expiry_date' => today()->addYear()->toDateString()]],
    ]);
    $batch = VaccineBatch::where('product_id', $product->id)->firstOrFail();
    $batch->update(['quantity_remaining' => 7]);
    $product->update(['quantity' => 7]);

    expect(fn () => app(PurchaseService::class)->cancelPurchase($order->fresh()))->toThrow(DomainException::class);
    expect((float) $batch->fresh()->quantity_remaining)->toBe(7.0)
        ->and((float) $product->fresh()->quantity)->toBe(7.0)
        ->and($order->fresh()->status)->toBe('confirmed');
});

// اختبار منع إلغاء دفعة لها مرجع فعلي في invoice_item_vaccine_batches.
it('blocks cancellation when a vaccine batch is referenced by an invoice item', function () {
    $this->actingAsAdmin();
    $product = Product::factory()->vaccination()->create();
    $order = app(PurchaseService::class)->createPurchaseOrder([
        'purchased_at' => today()->toDateString(),
        'items' => [['product_id' => $product->id, 'quantity' => 4, 'purchase_price_per_unit' => 5, 'selling_price_per_unit' => 10, 'expiry_date' => today()->addYear()->toDateString()]],
    ]);
    $batch = VaccineBatch::where('product_id', $product->id)->firstOrFail();
    $invoice = Invoice::factory()->create(['total' => 10]);
    $invoiceItem = InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10, 'line_total' => 10]);
    InvoiceItemVaccineBatch::create(['invoice_item_id' => $invoiceItem->id, 'vaccine_batch_id' => $batch->id, 'quantity' => 1]);

    expect(fn () => app(PurchaseService::class)->cancelPurchase($order->fresh()))->toThrow(DomainException::class);
    expect(VaccineBatch::find($batch->id))->not->toBeNull()
        ->and($order->fresh()->status)->toBe('confirmed');
});

// اختبار حذف دفعة اللقاح السليمة وإعادة كمية المنتج للصفر بدقة.
it('removes an untouched vaccine batch and recalculates product quantity', function () {
    $this->actingAsAdmin();
    $product = Product::factory()->vaccination()->create();
    $order = app(PurchaseService::class)->createPurchaseOrder([
        'purchased_at' => today()->toDateString(),
        'items' => [['product_id' => $product->id, 'quantity' => 4, 'purchase_price_per_unit' => 5, 'selling_price_per_unit' => 10, 'expiry_date' => today()->addYear()->toDateString()]],
    ]);
    $batchId = VaccineBatch::where('product_id', $product->id)->value('id');

    app(PurchaseService::class)->cancelPurchase($order->fresh());

    expect(VaccineBatch::find($batchId))->toBeNull()
        ->and((float) $product->fresh()->quantity)->toBe(0.0)
        ->and($order->fresh()->status)->toBe('cancelled');
});

// اختبار رجوع متوسط التكلفة للقيمة السابقة بدون فقدان الدقة.
it('restores the normal product average cost exactly', function () {
    $this->actingAsAdmin();
    $product = Product::factory()->product()->create(['quantity' => 10, 'average_cost' => 20]);
    $order = app(PurchaseService::class)->createPurchaseOrder([
        'purchased_at' => today()->toDateString(),
        'items' => [['product_id' => $product->id, 'quantity' => 10, 'purchase_price_per_unit' => 30, 'selling_price_per_unit' => 40]],
    ]);

    expect((float) $product->fresh()->average_cost)->toBe(25.0);
    app(PurchaseService::class)->cancelPurchase($order->fresh());
    expect((float) $product->fresh()->average_cost)->toBe(20.0)
        ->and((float) $product->fresh()->quantity)->toBe(10.0);
});

// اختبار رفض الإلغاء الثاني عند استخدام رمز مختلف بعد تغيير حالة الفاتورة.
it('rejects a second cancellation with a different token by status', function () {
    $admin = $this->actingAsAdmin();
    $product = Product::factory()->product()->create(['quantity' => 1]);
    $order = app(PurchaseService::class)->createPurchaseOrder(['purchased_at' => today()->toDateString(), 'items' => [['product_id' => $product->id, 'quantity' => 1, 'purchase_price_per_unit' => 5, 'selling_price_per_unit' => 10]]]);

    $this->post(route('purchases.cancel', $order), ['_submit_token' => 'cancel-token-one'])->assertRedirect();
    $second = $this->post(route('purchases.cancel', $order->fresh()), ['_submit_token' => 'cancel-token-two']);

    $second->assertSessionHas('error');
    expect($order->fresh()->status)->toBe('cancelled');
});

// اختبار منع التكرار عند إعادة إرسال نفس رمز الإلغاء.
it('cancels only once when the same token is submitted twice', function () {
    $this->actingAsAdmin();
    $product = Product::factory()->product()->create(['quantity' => 1]);
    $order = app(PurchaseService::class)->createPurchaseOrder(['purchased_at' => today()->toDateString(), 'items' => [['product_id' => $product->id, 'quantity' => 1, 'purchase_price_per_unit' => 5, 'selling_price_per_unit' => 10]]]);
    $payload = ['_submit_token' => 'cancel-token-same'];

    $this->post(route('purchases.cancel', $order), $payload)
        ->assertRedirect(route('purchases.show', $order))
        ->assertSessionHas('success');
    $this->post(route('purchases.cancel', $order->fresh()), $payload)
        ->assertRedirect(route('purchases.show', $order))
        ->assertSessionHas('info');

    expect($order->fresh()->status)->toBe('cancelled')
        ->and((float) $product->fresh()->quantity)->toBe(1.0);
});

// اختبار أن الموظف يحصل على نفس منع مسارات الإدارة الأخرى.
it('denies employees with the same redirect as other admin-only routes', function () {
    $employee = $this->actingAsEmployee();
    $product = Product::factory()->product()->create(['quantity' => 1]);
    $order = app(PurchaseService::class)->createPurchaseOrder(['purchased_at' => today()->toDateString(), 'items' => [['product_id' => $product->id, 'quantity' => 1, 'purchase_price_per_unit' => 5, 'selling_price_per_unit' => 10]]]);

    $response = $this->post(route('purchases.cancel', $order), ['_submit_token' => 'employee-cancel-token']);

    $response->assertRedirect('/');
    $response->assertSessionHas('error', 'ليس لديك صلاحية للوصول لهذه الصفحة');
    expect($order->fresh()->status)->toBe('confirmed');
});
