<?php

use App\Models\Animal;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vaccination;
use App\Services\InvoiceService;
use App\Services\PurchaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('admin', 'web');
    Role::findOrCreate('employee', 'web');
    $this->auditAdmin = User::factory()->create();
    $this->auditAdmin->assignRole('admin');
    $this->actingAs($this->auditAdmin);
});

test('baseline database and side effects are isolated', function () {
    expect(app()->environment())->toBe('testing')
        ->and(DB::connection()->getDriverName())->toBe('sqlite')
        ->and(DB::connection()->getDatabaseName())->toBe(':memory:')
        ->and(config('cache.default'))->toBe('array')
        ->and(config('session.driver'))->toBe('array')
        ->and(config('queue.default'))->toBe('sync')
        ->and(config('mail.default'))->toBe('array');
});

test('baseline admin pages render through middleware and Blade', function (string $path) {
    $this->get($path)->assertSuccessful();
})->with([
    '/', '/customers', '/customers/create', '/invoices', '/invoices/create',
    '/invoices?period=month', '/invoices?period=all', '/products', '/products/create',
    '/vaccinations', '/vaccine-batches', '/vaccine-batches/create', '/expenses',
    '/expenses/create', '/purchases', '/purchases/create', '/suppliers',
    '/suppliers/create', '/users', '/users/create', '/settings', '/api/notifications',
]);

test('baseline guests are redirected to login for protected pages', function (string $path) {
    auth()->logout();
    $this->get($path)->assertRedirect(route('login'));
})->with(['/', '/customers', '/invoices', '/products', '/settings', '/reports']);

test('baseline employees cannot open admin pages', function (string $path) {
    $employee = User::factory()->create();
    $employee->assignRole('employee');
    $this->actingAs($employee)->get($path)->assertRedirect('/');
})->with(['/settings', '/users', '/reports', '/suppliers', '/vaccine-batches', '/api/notifications']);

test('baseline record pages render with related data and outstanding payments', function () {
    $customer = Customer::factory()->create(['notes' => 'متابعة تجريبية']);
    $animal = Animal::factory()->for($customer)->create();
    $product = Product::factory()->product()->create();
    $invoice = Invoice::factory()->create([
        'customer_id' => $customer->id, 'animal_id' => $animal->id,
        'status' => 'confirmed', 'total' => 100, 'amount_paid' => 25,
    ]);
    InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id, 'product_id' => $product->id,
        'quantity' => 2, 'unit_price' => 50, 'line_total' => 100,
    ]);
    $invoice->payments()->create(['amount' => 25, 'paid_at' => now()]);
    $supplier = Supplier::create(['name' => 'Baseline supplier', 'is_active' => true]);
    $purchase = app(PurchaseService::class)->createPurchaseOrder([
        'supplier_id' => $supplier->id, 'purchased_at' => today()->toDateString(),
        'items' => [[
            'product_id' => $product->id, 'quantity' => 2,
            'purchase_price_per_unit' => 20, 'selling_price_per_unit' => 50,
        ]],
    ]);

    $this->get(route('customers.show', $customer))->assertSuccessful()
        ->assertSeeText([
            'تعديل البيانات',
            'حيوانات العميل',
            'متبقي على العميل',
            'إجمالي الفاتورة',
            'ملاحظات العميل',
            'سجل دفعات الفاتورة',
            'إجمالي المدفوع',
            'تسجيل دفعة جديدة',
        ])
        ->assertDontSee("\u{FEFF}", false);

    foreach ([
        route('customers.edit', $customer),
        route('customers.animals.index', $customer), route('animals.show', $animal),
        route('invoices.show', $invoice), route('products.edit', $product),
        route('purchases.show', $purchase), route('suppliers.edit', $supplier),
        route('users.edit', $this->auditAdmin),
        route('reports.month', ['year' => today()->year, 'month' => today()->month]),
    ] as $url) {
        $this->get($url)->assertSuccessful();
    }
});

test('baseline quick sale records cash and stock together', function () {
    $product = Product::factory()->product()->create(['quantity' => 10, 'average_cost' => 20]);
    $invoice = app(InvoiceService::class)->saveQuickSale([
        'customer_name' => 'Baseline buyer',
        'items' => [['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 50]],
    ]);

    expect((float) $invoice->total)->toBe(100.0)
        ->and((float) $invoice->amount_paid)->toBe(100.0)
        ->and((float) $invoice->payments()->sum('amount'))->toBe(100.0)
        ->and((float) $product->fresh()->quantity)->toBe(8.0)
        ->and($invoice->created_by)->toBe($this->auditAdmin->id);
});

test('baseline a failed quick sale rolls back all earlier items and payments', function () {
    $first = Product::factory()->product()->create(['quantity' => 10]);
    $second = Product::factory()->product()->create(['quantity' => 1]);

    expect(fn () => app(InvoiceService::class)->saveQuickSale([
        'items' => [
            ['product_id' => $first->id, 'quantity' => 2, 'unit_price' => 50],
            ['product_id' => $second->id, 'quantity' => 2, 'unit_price' => 50],
        ],
    ]))->toThrow(RuntimeException::class);

    expect((float) $first->fresh()->quantity)->toBe(10.0)
        ->and(Invoice::count())->toBe(0)
        ->and(InvoiceItem::count())->toBe(0);
    $this->assertDatabaseCount('invoice_payments', 0);
});

test('baseline regular purchases update weighted cost and supplier debt', function () {
    $product = Product::factory()->product()->create(['quantity' => 10, 'average_cost' => 20]);
    $supplier = Supplier::create(['name' => 'Baseline supplier']);
    $order = app(PurchaseService::class)->createPurchaseOrder([
        'supplier_id' => $supplier->id, 'purchased_at' => today()->toDateString(), 'amount_paid' => 100,
        'items' => [[
            'product_id' => $product->id, 'quantity' => 10,
            'purchase_price_per_unit' => 40, 'selling_price_per_unit' => 60,
        ]],
    ]);

    expect((float) $product->fresh()->quantity)->toBe(20.0)
        ->and((float) $product->fresh()->average_cost)->toBe(30.0)
        ->and((float) $order->total_cost)->toBe(400.0)
        ->and((float) $order->amount_paid)->toBe(100.0)
        ->and($order->remaining_amount)->toBe(300.0)
        ->and($supplier->fresh()->balance)->toBe(300.0);
});

test('baseline vaccine purchase creates a priced batch and sale stores its cost', function () {
    $product = Product::factory()->vaccination()->create();
    $order = app(PurchaseService::class)->createPurchaseOrder([
        'purchased_at' => today()->toDateString(),
        'items' => [[
            'product_id' => $product->id, 'quantity' => 10, 'purchase_price_per_unit' => 40,
            'selling_price_per_unit' => 100, 'expiry_date' => today()->addMonths(6)->toDateString(),
        ]],
    ]);
    $batch = $order->items->first()->batch;
    expect((float) $batch->purchase_price)->toBe(40.0)
        ->and((float) $product->fresh()->quantity)->toBe(10.0);

    $invoice = app(InvoiceService::class)->saveQuickSale([
        'items' => [['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 100]],
    ]);
    expect((float) $invoice->items->first()->cost_price_at_sale)->toBe(40.0)
        ->and((float) $batch->fresh()->quantity_remaining)->toBe(8.0);
});

test('baseline return distinguishes reducing debt from paying cash back', function (float $paid, float $cash, float $remaining) {
    $product = Product::factory()->product()->create(['quantity' => 6]);
    $invoice = Invoice::factory()->create(['status' => 'confirmed', 'total' => 400, 'amount_paid' => $paid]);
    $item = InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id, 'product_id' => $product->id,
        'quantity' => 4, 'unit_price' => 100, 'line_total' => 400,
    ]);
    $return = app(InvoiceService::class)->createReturn($invoice, [
        ['invoice_item_id' => $item->id, 'quantity_returned' => 1],
    ]);

    expect((float) $return->total_refund)->toBe(100.0)
        ->and((float) $return->cash_refunded)->toBe($cash)
        ->and((float) $invoice->fresh()->total)->toBe(300.0)
        ->and($invoice->fresh()->remaining_amount)->toBe($remaining)
        ->and((float) $product->fresh()->quantity)->toBe(7.0)
        ->and((float) $item->fresh()->quantity)->toBe(3.0);
})->with([
    'unpaid' => [0.0, 0.0, 300.0],
    'partial' => [200.0, 0.0, 100.0],
    'paid' => [400.0, 100.0, 0.0],
]);

test('baseline cash summaries use payment date and deduct only clinic supplier payments', function () {
    $invoice = Invoice::factory()->create([
        'status' => 'confirmed', 'total' => 500, 'amount_paid' => 200,
        'created_at' => today()->subMonth(),
    ]);
    $invoice->payments()->create(['amount' => 200, 'paid_at' => now()]);
    Expense::create(['title' => 'Baseline expense', 'amount' => 20, 'expense_date' => today()]);
    $order = PurchaseOrder::create([
        'order_number' => 'PO-BASELINE', 'total_cost' => 100, 'amount_paid' => 50,
        'purchased_at' => today(),
    ]);
    $order->payments()->create(['amount' => 30, 'paid_at' => today(), 'is_from_clinic_cash' => true]);
    $order->payments()->create(['amount' => 20, 'paid_at' => today(), 'is_from_clinic_cash' => false]);
    $invoice->returns()->create(['total_refund' => 10, 'cash_refunded' => 10]);

    $service = app(InvoiceService::class);
    foreach ([
        $service->getDailyRevenueSummary(today()->toDateString()),
        $service->getMonthlyRevenueSummary(today()->year, today()->month),
    ] as $summary) {
        expect($summary['gross_revenue'])->toBe(0.0)
            ->and($summary['actual_gross_revenue'])->toBe(200.0)
            ->and($summary['total_expenses'])->toBe(20.0)
            ->and($summary['supplier_cash_out'])->toBe(30.0)
            ->and($summary['cash_refunds_out'])->toBe(10.0)
            ->and($summary['net_cash_in_drawer'])->toBe(140.0);
    }
});

test('baseline ledger payment updates invoice and rejects overpayment', function () {
    $this->travelTo(\Carbon\Carbon::parse('2026-11-20 17:16:00', 'UTC'));
    $invoice = Invoice::factory()->create(['status' => 'confirmed', 'total' => 100, 'amount_paid' => 0]);
    // استخدام رمز مختلف لكل عملية دفع اختبارية.
    $this->postWithToken(route('invoice.payments.store', $invoice), ['amount' => 40])
        ->assertSessionHasNoErrors();
    expect((float) $invoice->fresh()->amount_paid)->toBe(40.0)
        ->and((float) $invoice->payments()->sum('amount'))->toBe(40.0);
    expect($invoice->payments()->first()->paid_at->equalTo(now()))->toBeTrue();

    // تجاوز الإجمالي يُعرض كرسالة جلسة عامة وليس كخطأ Form Request.
    $this->postWithToken(route('invoice.payments.store', $invoice), ['amount' => 61])
        ->assertSessionHasErrors(['amount' => 'المبلغ أكبر من المتبقي (60.00 ج)'], null, 'payment');
    expect((float) $invoice->fresh()->amount_paid)->toBe(40.0);
    expect($invoice->payments()->count())->toBe(1);
    $this->travelBack();
});

test('payment accepts an editable Cairo date and keeps validation errors in the payment form', function () {
    $invoice = Invoice::factory()->create(['status' => 'confirmed', 'total' => 100, 'amount_paid' => 0]);
    $this->postWithToken(route('invoice.payments.store', $invoice), [
        'amount' => 10,
        'paid_at' => '2026-11-19T19:30',
        'payment_invoice_id' => $invoice->id,
    ])->assertSessionHasNoErrors();
    expect($invoice->payments()->first()->paid_at->format('Y-m-d H:i'))->toBe('2026-11-19 17:30');

    $this->postWithToken(route('invoice.payments.store', $invoice), [
        'amount' => 91,
        'paid_at' => '2026-11-19T19:30',
        'payment_invoice_id' => $invoice->id,
    ])->assertSessionHasErrors(['amount' => 'المبلغ أكبر من المتبقي (90.00 ج)'], null, 'payment')
        ->assertSessionMissing('error')
        ->assertSessionHas('_old_input.paid_at', '2026-11-19T19:30');
    expect($invoice->payments()->count())->toBe(1);
});

test('baseline customer edit normalizes phone and rejects another customers phone', function () {
    $first = Customer::factory()->create(['phone' => '201011112222']);
    $second = Customer::factory()->create(['phone' => '201022223333']);
    $data = ['name' => 'Baseline customer', 'animal_type' => 'cat', 'phone' => '01011112222'];
    $this->put(route('customers.update', $first), $data)->assertSessionHasNoErrors();
    expect($first->fresh()->phone)->toBe('201011112222');

    $this->put(route('customers.update', $first), array_replace($data, ['phone' => '01022223333']))
        ->assertSessionHasErrors('phone');
    expect($first->fresh()->phone)->not->toBe($second->phone);
});

test('baseline vaccination completion preserves linked animal and invoice', function () {
    $vaccination = Vaccination::factory()->create(['is_completed' => false]);
    $this->postJson(route('vaccinations.complete', $vaccination))->assertOk()->assertJson(['success' => true]);
    expect($vaccination->fresh()->is_completed)->toBeTrue()
        ->and($vaccination->fresh()->animal_id)->toBe($vaccination->animal_id)
        ->and($vaccination->fresh()->invoice_id)->toBe($vaccination->invoice_id);
});

test('baseline login rejects invalid credentials then authenticates and logs out', function () {
    $user = $this->auditAdmin;
    $user->update(['password' => Hash::make('baseline-test-password')]);
    auth()->logout();
    $this->get('/login')->assertOk();
    $this->post('/login', ['email' => $user->email, 'password' => 'incorrect'])
        ->assertSessionHasErrors('email');
    $this->assertGuest();
    $this->post('/login', ['email' => $user->email, 'password' => 'baseline-test-password'])
        ->assertRedirect('/')->assertSessionHasNoErrors();
    $this->assertAuthenticatedAs($user);
    $this->post('/logout')->assertRedirect('/login');
    $this->assertGuest();
});

test('baseline a complete visit works through HTTP validation with a new animal', function () {
    Product::factory()->service()->create(['name' => 'كشف', 'is_active' => true]);
    $vaccine = Product::factory()->vaccination()->create();
    $batch = \App\Models\VaccineBatch::factory()->create([
        'product_id' => $vaccine->id, 'quantity_received' => 10, 'quantity_remaining' => 10,
    ]);
    // إرفاق رمز إرسال بطلب إنشاء الزيارة.
    $this->postWithToken(route('customers.store'), [
        'name' => 'Baseline visit', 'phone' => '01012345678', 'animal_type' => 'cat',
        'animal_id' => 'new_animal', 'new_animal' => ['name' => 'Milo', 'species' => 'cat'],
        'consultation_price' => 100, 'amount_paid' => 120,
        'vaccinations' => [[
            'vaccine_product_id' => $vaccine->id, 'vaccine_quantity' => 1, 'vaccine_unit_price' => 50,
            'vaccination_date' => today()->toDateString(), 'next_dose_date' => today()->addMonth()->toDateString(),
        ]],
    ])->assertRedirect(route('customers.index'))->assertSessionHasNoErrors();
    $invoice = Invoice::sole();
    expect((float) $invoice->total)->toBe(150.0)
        ->and((float) $invoice->amount_paid)->toBe(120.0)
        ->and((float) $invoice->payments()->sum('amount'))->toBe(120.0)
        ->and($invoice->customer->phone)->toBe('201012345678')
        ->and($invoice->animal->name)->toBe('Milo')
        ->and($invoice->vaccinations->sole()->animal_id)->toBe($invoice->animal_id)
        ->and((float) $batch->fresh()->quantity_remaining)->toBe(9.0);
});

test('baseline visits reject an animal owned by another customer without partial writes', function () {
    $customer = Customer::factory()->create(['phone' => '201012345678']);
    $animal = Animal::factory()->create();
    // إرفاق رمز إرسال بطلب الزيارة المرفوضة.
    $this->postWithToken(route('customers.store'), [
        'name' => $customer->name, 'phone' => '01012345678', 'animal_type' => 'cat',
        'animal_id' => $animal->id, 'consultation_price' => 0,
    ])->assertSessionHasErrors('animal_id');
    $this->assertDatabaseCount('invoices', 0);
});

test('baseline product creation applies stock rules for each product type', function (string $type, float $quantity, bool $tracked, string $status) {
    $this->post(route('products.store'), [
        'name' => 'Baseline '.$type, 'type' => $type, 'price' => 100,
        'quantity' => 5, 'low_stock_threshold' => 2, 'is_active' => true,
    ])->assertSessionHasNoErrors();
    $product = Product::sole();
    expect((float) $product->quantity)->toBe($quantity)
        ->and($product->track_stock)->toBe($tracked)
        ->and($product->stock_status)->toBe($status);
})->with([
    ['product', 5.0, true, 'available'],
    ['service', 0.0, false, 'available'],
    ['vaccination', 0.0, true, 'out_of_stock'],
]);

test('baseline referenced products are deactivated instead of deleted', function () {
    $item = InvoiceItem::factory()->create();
    $product = $item->product;
    $this->delete(route('products.destroy', $product))->assertRedirect(route('products.index'));
    expect($product->fresh())->not->toBeNull()
        ->and($product->fresh()->is_active)->toBeFalse();
});

test('baseline expense deletion is soft and removes it from cash summaries', function () {
    // إرفاق رمز الإرسال بطلب المصروف بعد تطبيق middleware idempotent.
    $this->postWithToken(route('expenses.store'), [
        'title' => 'Baseline rent', 'amount' => 50, 'expense_date' => today()->toDateString(),
    ])->assertSessionHasNoErrors();
    $expense = Expense::sole();
    expect(app(InvoiceService::class)->getDailyRevenueSummary(today()->toDateString())['total_expenses'])->toBe(50.0);
    $this->delete(route('expenses.destroy', $expense))->assertRedirect(route('expenses.index'));
    $this->assertSoftDeleted($expense);
    expect(app(InvoiceService::class)->getDailyRevenueSummary(today()->toDateString())['total_expenses'])->toBe(0.0);
});

test('baseline customer lookup and selected phone exports preserve normalized identity', function () {
    $customer = Customer::factory()->create(['phone' => '201012345678']);
    $animal = Animal::factory()->for($customer)->create();
    $this->getJson(route('customers.lookup-for-visit', ['phone' => '01012345678']))
        ->assertOk()->assertJsonPath('id', $customer->id)->assertJsonPath('animals.0.id', $animal->id);
    $this->getJson(route('customers.export-phones', ['ids' => [$customer->id, $customer->id]]))
        ->assertOk()->assertExactJson(['phones' => ['201012345678']]);
});

test('baseline selected customer CSV export returns a downloadable file', function () {
    $customer = Customer::factory()->create(['phone' => '201012345678']);
    $response = $this->post(route('customers.export-excel'), ['ids' => [$customer->id]]);
    $response->assertOk()->assertDownload();
    $csv = file_get_contents($response->baseResponse->getFile()->getPathname());
    expect($csv)->toContain('201012345678')->toContain($customer->name);
});

test('baseline profit reports separate purchase cash flow from cost of goods using date function adapters', function () {
    // The report SQL uses MySQL functions. These test-only adapters let SQLite
    // exercise report calculations and rendering; they do not verify MySQL locks.
    $pdo = DB::connection()->getPdo();
    $pdo->sqliteCreateFunction('MONTH', fn ($value) => (int) (new DateTimeImmutable($value))->format('n'));
    $pdo->sqliteCreateFunction('YEAR', fn ($value) => (int) (new DateTimeImmutable($value))->format('Y'));

    $invoice = Invoice::factory()->create(['status' => 'confirmed', 'total' => 200, 'amount_paid' => 0]);
    InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id, 'quantity' => 2, 'unit_price' => 100,
        'line_total' => 200, 'cost_price_at_sale' => 30,
    ]);
    Expense::create(['title' => 'Baseline expense', 'amount' => 20, 'expense_date' => today()]);
    $order = PurchaseOrder::create([
        'order_number' => 'PO-REPORT', 'total_cost' => 100, 'amount_paid' => 50, 'purchased_at' => today(),
    ]);
    $order->payments()->create(['amount' => 50, 'paid_at' => today(), 'is_from_clinic_cash' => true]);

    $this->get(route('reports.index'))->assertOk()
        ->assertViewHas('yearlyTotals', fn ($totals) => (float) $totals['revenue'] === 200.0
            && (float) $totals['net_profit'] === 120.0);
    $this->get(route('reports.profitability'))->assertOk()
        ->assertViewHas('cogs', fn ($value) => (float) $value === 60.0)
        ->assertViewHas('netProfit', fn ($value) => (float) $value === 120.0)
        ->assertViewHas('supplierCashYear', fn ($value) => (float) $value === 50.0);
    $this->get(route('reports.month', ['year' => today()->year, 'month' => today()->month]))
        ->assertOk()->assertViewHas('netProfit', fn ($value) => (float) $value === 120.0);
});
