<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Product;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

// إعداد Pest.php يربط اختبارات Feature بـ TestCase وRefreshDatabase تلقائيًا.

beforeEach(function (): void {
    $this->actingAsAdmin();
});

test('same token creates one customer visit and one invoice', function (): void {
    $this->consultationProduct(100);
    $token = (string) Str::uuid();
    $data = [
        '_submit_token' => $token,
        'name' => 'Batch 2 Customer',
        'phone' => '01033334444',
        'animal_type' => 'cat',
        'consultation_price' => 100,
    ];

    $this->post(route('customers.store'), $data)->assertRedirect();
    $this->post(route('customers.store'), $data)->assertRedirect();

    expect(Customer::count())->toBe(1)
        ->and(Invoice::count())->toBe(1);
});

test('same token creates one quick sale', function (): void {
    $product = Product::factory()->product()->create(['quantity' => 10]);
    $token = (string) Str::uuid();
    $data = [
        '_submit_token' => $token,
        'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10]],
    ];

    $this->post(route('invoices.store'), $data)->assertRedirect();
    $this->post(route('invoices.store'), $data)->assertRedirect();

    expect(Invoice::count())->toBe(1)
        ->and((float) $product->fresh()->quantity)->toBe(9.0);
});

test('same token creates one purchase order', function (): void {
    $product = Product::factory()->product()->create();
    $token = (string) Str::uuid();
    $data = [
        '_submit_token' => $token,
        'purchased_at' => today()->toDateString(),
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 1,
            'purchase_price_per_unit' => 5,
            'selling_price_per_unit' => 10,
        ]],
    ];

    $this->post(route('purchases.store'), $data)->assertRedirect();
    $this->post(route('purchases.store'), $data)->assertRedirect();

    expect(PurchaseOrder::count())->toBe(1);
});

test('different tokens create two legitimate operations', function (): void {
    $product = Product::factory()->product()->create(['quantity' => 10]);
    $base = ['items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10]]];

    $this->postWithToken(route('invoices.store'), $base)->assertRedirect();
    $this->postWithToken(route('invoices.store'), $base)->assertRedirect();

    expect(Invoice::count())->toBe(2)
        ->and((float) $product->fresh()->quantity)->toBe(8.0);
});

test('missing token rejects the request without creating a visit', function (): void {
    $this->consultationProduct(100);

    $this->post(route('customers.store'), [
        'name' => 'No Token',
        'phone' => '01033334444',
        'animal_type' => 'cat',
        'consultation_price' => 100,
    ])->assertRedirect();

    expect(Customer::count())->toBe(0)
        ->and(Invoice::count())->toBe(0);
});

test('validation failure releases the token for a valid retry', function (): void {
    $this->consultationProduct(100);
    $token = (string) Str::uuid();

    $this->post(route('customers.store'), [
        '_submit_token' => $token,
        'phone' => '01033334444',
        'animal_type' => 'cat',
        'consultation_price' => 100,
    ])->assertSessionHasErrors('name');

    $this->post(route('customers.store'), [
        '_submit_token' => $token,
        'name' => 'Retry Customer',
        'phone' => '01033334444',
        'animal_type' => 'cat',
        'consultation_price' => 100,
    ])->assertRedirect();

    expect(Customer::count())->toBe(1)
        ->and(Invoice::count())->toBe(1);
});

test('same token records one invoice payment', function (): void {
    $invoice = Invoice::factory()->create(['status' => 'confirmed', 'total' => 100, 'amount_paid' => 0]);
    $token = (string) Str::uuid();
    $data = ['_submit_token' => $token, 'amount' => 50];

    $this->post(route('invoice.payments.store', $invoice), $data)->assertRedirect();
    $this->post(route('invoice.payments.store', $invoice), $data)->assertRedirect();

    expect(InvoicePayment::where('invoice_id', $invoice->id)->count())->toBe(1)
        ->and((float) $invoice->fresh()->amount_paid)->toBe(50.0);
});

test('protected forms render an idempotency token and double-submit marker', function (): void {
    foreach ([
        route('customers.create'),
        route('invoices.create'),
        route('purchases.create'),
    ] as $url) {
        $response = $this->get($url)->assertSuccessful();
        $response->assertSee('name="_submit_token"', false)
            ->assertSee('data-prevent-double', false);
    }
});

test('file cache add accepts the first key and rejects the second sequentially', function (): void {
    $directory = sys_get_temp_dir().'\\betscornar-cache-'.Str::uuid();
    $originalDefault = config('cache.default');
    $originalPath = config('cache.stores.file.path');

    try {
        config([
            'cache.default' => 'file',
            'cache.stores.file.path' => $directory,
        ]);

        expect(Cache::add('batch2-file-key', 'value', 300))->toBeTrue()
            ->and(Cache::add('batch2-file-key', 'value', 300))->toBeFalse();
    } finally {
        config([
            'cache.default' => $originalDefault,
            'cache.stores.file.path' => $originalPath,
        ]);
        File::deleteDirectory($directory);
    }
});
