<?php

use App\Helpers\PhoneHelper;
use App\Models\Animal;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Vaccination;
use App\Models\VaccineBatch;
use App\Services\CustomerVisitService;
use App\Services\InvoiceService;
use App\Services\PurchaseService;
use App\Services\StockService;
function qaVaccine(array $attributes = []): Product
{
    $product = Product::factory()->vaccination()->create($attributes);
    VaccineBatch::factory()->create([
        'product_id' => $product->id,
        'expiry_date' => now()->addMonths(6)->toDateString(),
        'quantity_received' => 20,
        'quantity_remaining' => 20,
        'purchase_price' => 25,
        'selling_price' => 100,
    ]);
    app(StockService::class)->recalculateVaccineStock($product);

    return $product->fresh();
}

test('visit reuses a normalized customer and records its initial payment', function () {
    $this->consultationProduct(100);
    $service = app(CustomerVisitService::class);

    $first = $service->saveVisit([
        'name' => 'QA Client', 'phone' => '01012345678', 'animal_type' => 'cat',
        'consultation_price' => 100, 'amount_paid' => 50,
    ]);
    $second = $service->saveVisit([
        'name' => 'QA Client', 'phone' => '+20 101 234 5678', 'animal_type' => 'cat',
        'consultation_price' => 100,
    ]);

    expect(Customer::count())->toBe(1)
        ->and($first->customer_id)->toBe($second->customer_id)
        ->and((float) $first->payments()->sum('amount'))->toBe(50.0)
        ->and((float) $first->total)->toBe(100.0);
});

test('visit rejects an animal belonging to another customer without writes', function () {
    $this->consultationProduct(100);
    $owner = Customer::factory()->create();
    $animal = Animal::factory()->for($owner)->create();
    $service = app(CustomerVisitService::class);

    expect(fn () => $service->saveVisit([
        'name' => 'Other Client', 'phone' => '01022223333', 'animal_type' => 'cat',
        'animal_id' => $animal->id, 'consultation_price' => 100,
    ]))->toThrow(\Illuminate\Validation\ValidationException::class);

    expect(Customer::where('phone', '201022223333')->exists())->toBeFalse()
        ->and(Invoice::count())->toBe(0);
});

test('quick sale deducts normal and vaccine stock, records payment, and creates no vaccination', function () {
    $normal = Product::factory()->product()->create(['quantity' => 10, 'average_cost' => 20]);
    $vaccine = qaVaccine();
    $invoice = app(InvoiceService::class)->saveQuickSale([
        'items' => [
            ['product_id' => $normal->id, 'quantity' => 2, 'unit_price' => 50],
            ['product_id' => $vaccine->id, 'quantity' => 3, 'unit_price' => 100],
        ],
    ]);

    expect((float) $invoice->total)->toBe(400.0)
        ->and((float) $invoice->amount_paid)->toBe(400.0)
        ->and((float) $invoice->payments()->sum('amount'))->toBe(400.0)
        ->and((float) $normal->fresh()->quantity)->toBe(8.0)
        ->and((float) $vaccine->fresh()->quantity)->toBe(17.0)
        ->and(Vaccination::where('invoice_id', $invoice->id)->count())->toBe(0)
        ->and((float) $invoice->items()->where('product_id', $normal->id)->first()->cost_price_at_sale)->toBe(20.0);
});

test('quick sale with insufficient stock rolls back the invoice and earlier stock changes', function () {
    $normal = Product::factory()->product()->create(['quantity' => 10]);
    $short = Product::factory()->product()->create(['quantity' => 1]);

    expect(fn () => app(InvoiceService::class)->saveQuickSale([
        'items' => [
            ['product_id' => $normal->id, 'quantity' => 2, 'unit_price' => 10],
            ['product_id' => $short->id, 'quantity' => 2, 'unit_price' => 10],
        ],
    ]))->toThrow(RuntimeException::class);

    expect(Invoice::count())->toBe(0)
        ->and((float) $normal->fresh()->quantity)->toBe(10.0)
        ->and(InvoicePayment::count())->toBe(0);
});

test('partial return updates invoice math, restores normal stock, and calculates cash refund', function () {
    $product = Product::factory()->product()->create(['quantity' => 5]);
    $invoice = app(InvoiceService::class)->saveQuickSale([
        'items' => [['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 100]],
    ]);
    $return = app(InvoiceService::class)->createReturn($invoice->fresh(), [
        ['invoice_item_id' => $invoice->items->first()->id, 'quantity_returned' => 1],
    ]);

    expect((float) $return->total_refund)->toBe(100.0)
        ->and((float) $return->cash_refunded)->toBe(100.0)
        ->and((float) $invoice->fresh()->total)->toBe(100.0)
        ->and((float) $product->fresh()->quantity)->toBe(4.0);
});

test('return rejects an invoice item belonging to another invoice', function () {
    $product = Product::factory()->product()->create(['quantity' => 10]);
    $target = app(InvoiceService::class)->saveQuickSale([
        'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100]],
    ]);
    $other = app(InvoiceService::class)->saveQuickSale([
        'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 50]],
    ]);

    expect(fn () => app(InvoiceService::class)->createReturn($target->fresh(), [
        ['invoice_item_id' => $other->items->first()->id, 'quantity_returned' => 1],
    ]))->toThrow(RuntimeException::class);
});

test('normal stock transitions and cost snapshot are preserved', function () {
    $product = Product::factory()->product()->create([
        'quantity' => 2, 'average_cost' => 30, 'low_stock_threshold' => 2, 'stock_status' => 'low',
    ]);
    $item = InvoiceItem::factory()->create(['product_id' => $product->id, 'quantity' => 1]);
    $stock = app(StockService::class);

    $stock->decreaseStock($product->fresh(), 1, $item);
    expect((float) $product->fresh()->quantity)->toBe(1.0)
        ->and($product->fresh()->stock_status)->toBe('low')
        ->and((float) $item->fresh()->cost_price_at_sale)->toBe(30.0);

    $stock->increaseStock($product->fresh(), 2);
    expect((float) $product->fresh()->quantity)->toBe(3.0)
        ->and($product->fresh()->stock_status)->toBe('available');
});

test('phone normalization documents valid Egyptian formats and invalid input behavior', function () {
    expect(PhoneHelper::normalize('01012345678'))->toBe('201012345678')
        ->and(PhoneHelper::normalize('+20 10-1234-5678'))->toBe('201012345678')
        ->and(PhoneHelper::normalize('201012345678'))->toBe('201012345678')
        ->and(PhoneHelper::normalize(''))->toBe('20')
        ->and(PhoneHelper::normalize('abc'))->toBe('20');
});

test('weighted average cost and vaccine purchase behavior are recorded', function () {
    $product = Product::factory()->product()->create(['quantity' => 10, 'average_cost' => 20]);
    $order = app(PurchaseService::class)->createPurchaseOrder([
        'purchased_at' => today()->toDateString(),
        'items' => [['product_id' => $product->id, 'quantity' => 10, 'purchase_price_per_unit' => 40, 'selling_price_per_unit' => 60]],
    ]);

    expect((float) $order->total_cost)->toBe(400.0)
        ->and((float) $product->fresh()->average_cost)->toBe(30.0)
        ->and((float) $product->fresh()->quantity)->toBe(20.0);

    $vaccine = Product::factory()->vaccination()->create();
    $vaccineOrder = app(PurchaseService::class)->createPurchaseOrder([
        'purchased_at' => today()->toDateString(),
        'items' => [['product_id' => $vaccine->id, 'quantity' => 4, 'purchase_price_per_unit' => 25, 'selling_price_per_unit' => 100, 'expiry_date' => today()->addMonth()->toDateString()]],
    ]);

    expect($vaccineOrder->items->first()->batch)->not->toBeNull()
        ->and((float) $vaccine->fresh()->quantity)->toBe(4.0);
});

test('vaccine purchase without expiry records the actual current behavior', function () {
    $vaccine = Product::factory()->vaccination()->create();
    $thrown = null;

    try {
        app(PurchaseService::class)->createPurchaseOrder([
            'purchased_at' => today()->toDateString(),
            'items' => [['product_id' => $vaccine->id, 'quantity' => 1, 'purchase_price_per_unit' => 10, 'selling_price_per_unit' => 20]],
        ]);
    } catch (Throwable $exception) {
        $thrown = $exception;
    }

    expect($thrown)->toBeInstanceOf(Throwable::class)
        ->and(PurchaseOrder::count())->toBe(0)
        ->and(VaccineBatch::count())->toBe(0);
});

test('purchase payment aggregate is recalculated from payment rows', function () {
    $product = Product::factory()->product()->create();
    $order = app(PurchaseService::class)->createPurchaseOrder([
        'purchased_at' => today()->toDateString(),
        'items' => [['product_id' => $product->id, 'quantity' => 2, 'purchase_price_per_unit' => 50, 'selling_price_per_unit' => 80]],
    ]);
    app(PurchaseService::class)->addPayment($order, ['amount' => 40, 'paid_at' => today()->toDateString()]);

    expect((float) $order->fresh()->amount_paid)->toBe(40.0)
        ->and((float) $order->fresh()->remaining_amount)->toBe(60.0);
});

test('admin-only routes reject employees', function () {
    $this->actingAsEmployee();

    foreach (['/reports', '/settings', '/users', '/suppliers', '/vaccine-batches', '/settings/backup'] as $path) {
        $this->get($path)->assertRedirect('/');
    }
});

test('debug admin endpoint is not publicly available', function () {
    $this->get('/debug-admin')->assertNotFound();
});

// endpoint القديم حُذف، لذلك يجب أن يعيد الرابط القديم 404.
test('legacy payment endpoint is removed', function () {
    $this->actingAsAdmin();
    $invoice = Invoice::factory()->create(['status' => 'confirmed', 'total' => 100, 'amount_paid' => 0]);

    $this->post('/invoices/'.$invoice->id.'/pay', ['amount' => 50])->assertNotFound();
});

test('duplicate submissions are measured for visit, quick sale, and purchase order', function () {
    $this->consultationProduct(100);
    $visitData = ['name' => 'Duplicate', 'phone' => '01033334444', 'animal_type' => 'cat', 'consultation_price' => 100];
    app(CustomerVisitService::class)->saveVisit($visitData);
    app(CustomerVisitService::class)->saveVisit($visitData);

    $product = Product::factory()->product()->create(['quantity' => 10]);
    $saleData = ['items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10]]];
    app(InvoiceService::class)->saveQuickSale($saleData);
    app(InvoiceService::class)->saveQuickSale($saleData);

    $purchaseData = ['purchased_at' => today()->toDateString(), 'items' => [['product_id' => $product->id, 'quantity' => 1, 'purchase_price_per_unit' => 5, 'selling_price_per_unit' => 10]]];
    app(PurchaseService::class)->createPurchaseOrder($purchaseData);
    app(PurchaseService::class)->createPurchaseOrder($purchaseData);

    expect(Customer::where('phone', '201033334444')->count())->toBe(1)
        ->and(Invoice::count())->toBe(4)
        ->and(PurchaseOrder::count())->toBe(2);
});

// قرار العمل الحالي يسمح للموظف بفتح فواتير الموظفين الآخرين.
test('employee can access another employee invoice', function () {
    $employee = $this->actingAsEmployee();
    $other = \App\Models\User::factory()->create();
    $other->assignRole('employee');
    $invoice = Invoice::factory()->create(['created_by' => $other->id, 'status' => 'confirmed']);

    $response = $this->get(route('invoices.show', $invoice));
    $response->assertSuccessful();
});
