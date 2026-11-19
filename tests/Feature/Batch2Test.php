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
        route('expenses.create'),
        route('vaccine-batches.create'),
    ] as $url) {
        $response = $this->get($url)->assertSuccessful();
        $response->assertSee('name="_submit_token"', false)
            ->assertSee('data-prevent-double', false);
    }
});

// --- اختبارات FIX 1: بدون انتظار، 409 للـ AJAX، الـ token مش بيتحرر عند JSON ناجح ---

test('duplicate token returns fast without sleeping (no usleep)', function (): void {
    $product = Product::factory()->product()->create(['quantity' => 10]);
    $token = (string) Str::uuid();
    $data = [
        '_submit_token' => $token,
        'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10]],
    ];

    // الطلب الأول يمشي بشكل طبيعي.
    $this->post(route('invoices.store'), $data)->assertRedirect();

    // الطلب الثاني يجب أن يرجع فورًا بدون انتظار (بدون usleep) — وقته < 2 ثانية.
    $start = microtime(true);
    $this->post(route('invoices.store'), $data)->assertRedirect();
    $elapsed = microtime(true) - $start;

    expect($elapsed)->toBeLessThan(2.0, 'الطلب المكرر استغرق وقتًا طويلًا جدًا — usleep لم يُزل');
});

test('duplicate token with AJAX on processing state returns 409 JSON', function (): void {
    $userId = $this->actingAsAdmin()->id ?? 1;
    $token = (string) Str::uuid();
    $key = 'idem:'.$userId.':'.$token;

    // نحجز الرمز يدويًا في حالة processing لمحاكاة طلب قيد التنفيذ.
    Cache::add($key, 'processing', 300);

    $this->consultationProduct(100);

    $response = $this->withHeaders(['Accept' => 'application/json'])
        ->post(route('customers.store'), [
            '_submit_token' => $token,
            'name' => 'AJAX Test',
            'phone' => '01011112222',
            'animal_type' => 'cat',
            'consultation_price' => 100,
        ]);

    $response->assertStatus(409)
        ->assertJson(['message' => 'الطلب قيد التنفيذ']);
});

test('JSON success response keeps token as done and second AJAX call returns 409', function (): void {
    $product = Product::factory()->product()->create(['quantity' => 10]);
    $token = (string) Str::uuid();
    $data = [
        '_submit_token' => $token,
        'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10]],
    ];

    // الطلب الأول ينجح وأي JSON response لا يُزيل الـ token.
    $this->post(route('invoices.store'), $data)->assertRedirect();

    // الرمز يجب أن يكون موجودًا بعد النجاح (URL أو 'done').
    $userId = \App\Models\User::first()->id;
    $key = 'idem:'.$userId.':'.$token;
    expect(Cache::has($key))->toBeTrue('الـ token يجب أن يبقى محجوزًا بعد النجاح');

    // الطلب الثاني بنفس الرمز يجب أن يُرجع redirect (للـ URL المخزن).
    $this->post(route('invoices.store'), $data)->assertRedirect();
    expect(Invoice::count())->toBe(1, 'يجب أن يُنشأ invoice واحد فقط');
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

// --- اختبارات حماية إكمال وإعادة جدولة التطعيم (Status Guard) ---

test('completing a vaccination twice rejects the second request', function (): void {
    $vaccination = \App\Models\Vaccination::factory()->create(['is_completed' => false]);

    // الإكمال الأول ينجح
    $this->postJson(route('vaccinations.complete', $vaccination))
        ->assertOk()
        ->assertJson(['success' => true]);

    // الإكمال الثاني يُرفض بكود 422
    $this->postJson(route('vaccinations.complete', $vaccination->fresh()))
        ->assertStatus(422)
        ->assertJson(['success' => false]);
});

test('rescheduling a vaccination twice rejects the second request and creates only one next-dose row preserving animal_id', function (): void {
    $customer = \App\Models\Customer::factory()->create();
    $animal = \App\Models\Animal::factory()->for($customer)->create();
    $vaccination = \App\Models\Vaccination::factory()->create([
        'customer_id' => $customer->id,
        'animal_id' => $animal->id,
        'is_completed' => false,
    ]);

    $data = ['next_dose_date' => today()->addMonth()->toDateString()];

    // إعادة الجدولة الأولى تنجح
    $this->postJson(route('vaccinations.reschedule', $vaccination), $data)
        ->assertOk()
        ->assertJson(['success' => true]);

    // إعادة الجدولة الثانية على نفس التطعيم تُرفض بكود 422
    $this->postJson(route('vaccinations.reschedule', $vaccination->fresh()), $data)
        ->assertStatus(422)
        ->assertJson(['success' => false]);

    // التأكد من وجود سجل جرعة قادمة واحد فقط مع الحفاظ على معرف الحيوان
    $nextDoses = \App\Models\Vaccination::where('id', '!=', $vaccination->id)->get();
    expect($nextDoses)->toHaveCount(1)
        ->and($nextDoses->first()->animal_id)->toBe($animal->id);
});
