<?php

// Deliberately outside the default suites: these assertions describe required
// integrity guarantees and reproduce existing defects. Run separately with
// scripts/test-isolated.ps1 -Suite findings.

use App\Models\Animal;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\PurchasePayment;
use App\Models\User;
use App\Models\Vaccination;
use App\Models\VaccineBatch;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class)->group('audit-findings');

beforeEach(function () {
    Role::findOrCreate('admin', 'web');
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);
});

test('AUD-01 regular sales preserve purchase cost for profitability reports', function () {
    $product = Product::factory()->product()->create(['quantity' => 10, 'average_cost' => 40]);
    $invoice = app(InvoiceService::class)->saveQuickSale([
        'items' => [['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 100]],
    ]);
    expect((float) $invoice->items->first()->cost_price_at_sale)->toBe(40.0);
});

test('AUD-02 a return cannot consume an item from another invoice', function () {
    $target = Invoice::factory()->create(['status' => 'confirmed', 'total' => 100, 'amount_paid' => 100]);
    $other = Invoice::factory()->create(['status' => 'confirmed', 'total' => 100, 'amount_paid' => 100]);
    $item = InvoiceItem::factory()->create([
        'invoice_id' => $other->id, 'quantity' => 1, 'unit_price' => 100, 'line_total' => 100,
    ]);
    // إرفاق رمز الإرسال بطلب المرتجع الاختباري.
    $this->postWithToken(route('invoice-returns.store', $target), [
        'items' => [['invoice_item_id' => $item->id, 'quantity_returned' => 1]],
    ]);
    expect((float) $target->fresh()->total)->toBe(100.0)
        ->and((float) $item->fresh()->quantity)->toBe(1.0)
        ->and($target->returns()->count())->toBe(0);
});

test('AUD-03 repeated partial vaccine returns restore exactly the quantity sold', function () {
    $product = Product::factory()->vaccination()->create();
    $batch = VaccineBatch::factory()->create([
        'product_id' => $product->id, 'quantity_received' => 10, 'quantity_remaining' => 10,
    ]);
    $service = app(InvoiceService::class);
    $invoice = $service->saveQuickSale([
        'items' => [['product_id' => $product->id, 'quantity' => 4, 'unit_price' => 100]],
    ]);
    $item = $invoice->items->first();
    foreach ([1, 2] as $attempt) {
        $service->createReturn($invoice->fresh(), [['invoice_item_id' => $item->id, 'quantity_returned' => 1]]);
    }
    expect((float) $batch->fresh()->quantity_remaining)->toBe(8.0);
});

test('AUD-04 cancelling after a partial vaccine return cannot inflate inventory', function () {
    $product = Product::factory()->vaccination()->create();
    $batch = VaccineBatch::factory()->create([
        'product_id' => $product->id, 'quantity_received' => 10, 'quantity_remaining' => 10,
    ]);
    $service = app(InvoiceService::class);
    $invoice = $service->saveQuickSale([
        'items' => [['product_id' => $product->id, 'quantity' => 4, 'unit_price' => 100]],
    ]);
    $service->createReturn($invoice, [['invoice_item_id' => $invoice->items->first()->id, 'quantity_returned' => 1]]);
    $service->cancelInvoice($invoice->fresh(), 'Audit cancellation');
    expect((float) $batch->fresh()->quantity_remaining)->toBe(10.0);
});

test('AUD-05 legacy pay endpoint remains removed', function () {
    $invoice = Invoice::factory()->create(['status' => 'confirmed', 'total' => 100, 'amount_paid' => 0]);
    // endpoint القديم يجب أن يظل غير موجود بعد توحيد مسار الدفعات.
    $this->post('/invoices/'.$invoice->id.'/pay', ['amount' => 50])->assertNotFound();
});

test('AUD-06 purchase creation preserves the initial clinic cash checkbox', function () {
    $product = Product::factory()->product()->create();
    // إرفاق رمز الإرسال بطلب إنشاء الشراء.
    $this->postWithToken(route('purchases.store'), [
        'purchased_at' => today()->toDateString(), 'amount_paid' => 50, 'is_from_clinic_cash' => true,
        'items' => [[
            'product_id' => $product->id, 'quantity' => 2,
            'purchase_price_per_unit' => 50, 'selling_price_per_unit' => 100,
        ]],
    ])->assertSessionHasNoErrors();
    expect(PurchasePayment::sole()->is_from_clinic_cash)->toBeTrue();
});

test('AUD-07 a cancelled invoice cannot accept ledger payments', function () {
    $invoice = Invoice::factory()->create(['status' => 'cancelled', 'total' => 100, 'amount_paid' => 0]);
    // إرفاق رمز الإرسال بطلب الدفع على فاتورة ملغاة.
    $this->postWithToken(route('invoice.payments.store', $invoice), ['amount' => 50]);
    expect((float) $invoice->fresh()->amount_paid)->toBe(0.0)
        ->and($invoice->payments()->count())->toBe(0);
});

test('AUD-08 rescheduling preserves the patients animal relationship', function () {
    $customer = Customer::factory()->create();
    $animal = Animal::factory()->for($customer)->create();
    $vaccination = Vaccination::factory()->create([
        'customer_id' => $customer->id, 'animal_id' => $animal->id, 'is_completed' => false,
    ]);
    $this->postJson(route('vaccinations.reschedule', $vaccination), [
        'next_dose_date' => today()->addMonth()->toDateString(),
    ])->assertOk();
    $next = Vaccination::where('id', '!=', $vaccination->id)->sole();
    expect($next->animal_id)->toBe($animal->id);
});

test('AUD-09 stale invoice instances cannot restore the same stock twice', function () {
    $product = Product::factory()->product()->create(['quantity' => 10]);
    $service = app(InvoiceService::class);
    $invoice = $service->saveQuickSale([
        'items' => [['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 100]],
    ]);
    $stale = $invoice->fresh();
    $service->cancelInvoice($invoice, 'First request');
    try {
        $service->cancelInvoice($stale, 'Stale request');
    } catch (RuntimeException $exception) {
        // Either reject the stale request or make it idempotent.
    }
    expect((float) $product->fresh()->quantity)->toBe(10.0);
});

test('AUD-10 visit creation rejects a payment larger than the invoice total', function () {
    Product::factory()->service()->create(['name' => 'كشف', 'is_active' => true]);
    // إرفاق رمز الإرسال بطلب الزيارة الذي يختبر منع المدفوع الزائد.
    $this->postWithToken(route('customers.store'), [
        'name' => 'Audit buyer', 'phone' => '01012345678', 'animal_type' => 'cat',
        'consultation_price' => 100, 'amount_paid' => 1000,
    ])->assertSessionHasErrors('amount_paid');
    $this->assertDatabaseCount('invoices', 0);
});
