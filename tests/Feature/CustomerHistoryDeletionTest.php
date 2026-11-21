<?php

use App\Models\Animal;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Vaccination;
use App\Models\VaccineBatch;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

// اثبت أن الحذف الطبي لا يغيّر أي سجل مالي أو كمية مخزون وأن تحصيل الدين يظل متاحًا.
it('deletes medical history and still collects the hidden debt without affecting stock or reports', function () {
    $this->travelTo(now()->startOfDay()->addHours(12));
    $admin = $this->actingAsAdmin();
    $customer = Customer::factory()->create(['name' => 'عميل اختبار للحذف', 'phone' => '201012345678']);
    $animal = Animal::factory()->for($customer)->create();
    $product = Product::factory()->vaccination()->create(['quantity' => 8]);
    $batch = VaccineBatch::factory()->for($product)->create([
        'quantity_received' => 10, 'quantity_remaining' => 8, 'expiry_date' => today()->addMonth(),
    ]);
    $invoice = Invoice::factory()->for($customer)->for($animal)->create([
        'customer_name' => 'الاسم المحفوظ على الفاتورة', 'source' => 'customer', 'total' => 200, 'amount_paid' => 50,
    ]);
    $quickSale = Invoice::factory()->for($customer)->create(['source' => 'quick_sale', 'total' => 50, 'amount_paid' => 50]);
    $item = InvoiceItem::factory()->for($invoice)->for($product)->create([
        'quantity' => 2, 'unit_price' => 100, 'line_total' => 200, 'cost_price_at_sale' => 30,
    ]);
    $allocation = $item->vaccineBatches()->create(['vaccine_batch_id' => $batch->id, 'quantity' => 2]);
    $originalPayment = $invoice->payments()->create(['amount' => 50, 'paid_at' => now()]);
    $return = $invoice->returns()->create(['total_refund' => 0, 'cash_refunded' => 0, 'created_by' => $admin->id]);
    $vaccination = Vaccination::factory()->for($customer)->for($animal)->for($product)->for($invoice)->create([
        'vaccination_date' => today(), 'next_dose_date' => today()->addDay(), 'is_completed' => false,
    ]);

    // دع تقارير MySQL تعمل كذلك على قاعدة SQLite المعزولة أثناء الاختبارات.
    if (DB::connection()->getDriverName() === 'sqlite') {
        $pdo = DB::connection()->getPdo();
        $pdo->sqliteCreateFunction('YEAR', fn ($value) => (int) (new DateTimeImmutable($value))->format('Y'));
        $pdo->sqliteCreateFunction('MONTH', fn ($value) => (int) (new DateTimeImmutable($value))->format('n'));
    }

    // اطلب صفحات التقارير الحقيقية وأعد حساب ربحية الشهر بعد كل قراءة.
    $monthUrl = route('reports.month', ['year' => today()->year, 'month' => today()->month]);
    $profitabilityUrl = route('reports.profitability', ['year' => today()->year]);
    $financialSnapshot = function () use ($monthUrl, $profitabilityUrl, $product, $batch) {
        Cache::forget('report-profitability-months-'.today()->year);
        $dashboard = $this->get(route('dashboard'))->assertOk();
        $month = $this->get($monthUrl)->assertOk();
        $profitability = $this->get($profitabilityUrl)->assertOk();
        $annual = $this->get(route('reports.index', ['year' => today()->year]))->assertOk();

        return [
            'product' => (float) $product->fresh()->quantity,
            'batch' => (float) $batch->fresh()->quantity_remaining,
            'daily_revenue' => (float) $dashboard->viewData('todayRevenue'),
            'daily_visits' => $dashboard->viewData('todayVisits'),
            'monthly_summary' => $month->viewData('revenueSummary'),
            'monthly_cogs' => (float) $month->viewData('cogs'),
            'monthly_profit' => (float) $month->viewData('netProfit'),
            'profitability_months' => $profitability->viewData('months'),
            'annual_totals' => $annual->viewData('yearlyTotals'),
        ];
    };

    $this->get(route('customers.show', $customer))->assertOk()
        ->assertSeeText('مسح العميل وسجله بالكامل')
        ->assertSeeText('الفواتير التي ستُخفى: 2')->assertSeeText('الحيوانات التي ستُحذف: 1')
        ->assertSeeText('التطعيمات التي ستُحذف: 1')->assertSee('data-expected-phone="201012345678"', false);
    $before = $financialSnapshot();
    expect($before['daily_revenue'])->toBe(250.0)->and($before['daily_visits'])->toBe(2)
        ->and($before['monthly_cogs'])->toBe(60.0)->and($before['monthly_profit'])->toBe(190.0)
        ->and($before['product'])->toBe(8.0)->and($before['batch'])->toBe(8.0);
    expect(Cache::get(dashboardKey('upcoming_vaccinations'))->modelKeys())->toContain($vaccination->id);

    // نفّذ الطلب الفعلي بصلاحية المدير ورقم العميل الصحيح.
    $this->delete(route('customers.destroy', $customer), ['phone_confirmation' => $customer->phone])
        ->assertRedirect(route('customers.index'))->assertSessionHas('success');

    $this->assertSoftDeleted($customer);
    $this->assertDatabaseHas('customers', ['id' => $customer->id, 'phone' => 'deleted_'.$customer->id.'_'.$customer->phone]);
    $this->assertModelMissing($animal)->assertModelMissing($vaccination);
    foreach ([$invoice, $quickSale] as $deletedCustomerInvoice) {
        $this->assertDatabaseHas('invoices', [
            'id' => $deletedCustomerInvoice->id, 'customer_id' => $customer->id, 'animal_id' => null, 'is_hidden' => true,
        ]);
    }
    $this->assertModelExists($item)->assertModelExists($originalPayment)->assertModelExists($return)->assertModelExists($allocation);
    expect(Cache::has(dashboardKey('upcoming_vaccinations')))->toBeFalse();
    expect($financialSnapshot())->toBe($before);
    expect(Cache::get(dashboardKey('upcoming_vaccinations')))->toBeEmpty();

    // تحقق من اختفاء العميل والفواتير من مسارات العرض والبحث المنفصلة.
    $this->get(route('customers.index', ['q' => $customer->name]))->assertOk()->assertDontSeeText($customer->name);
    $this->getJson(route('customers.search', ['q' => $customer->name]))->assertExactJson([]);
    $this->getJson(route('customers.search', ['q' => $customer->phone]))->assertExactJson([]);
    $this->get(route('customers.show', $customer))->assertNotFound();
    $this->get(route('invoices.show', $invoice))->assertNotFound();
    $this->get(route('invoices.index', ['q' => $invoice->invoice_number, 'period' => 'all']))
        ->assertOk()->assertViewHas('invoices', fn ($rows) => $rows->isEmpty());

    // استخدم مسار الدفع نفسه من التقرير، وتحقق من بقاء الدين باسم العميل وقت الفاتورة.
    $this->get($profitabilityUrl)->assertOk()->assertSeeText($invoice->customer_name)
        ->assertSee('data-bs-target="#debtPayment'.$invoice->id.'"', false)
        ->assertSee('action="'.route('invoice.payments.store', $invoice).'"', false)
        ->assertViewHas('totalCustomerDebts', fn ($total) => (float) $total === 150.0)
        ->assertViewHas('customerDebts', fn ($debts) => $debts->first()?->customer?->trashed() === true);
    $this->from($profitabilityUrl)->postWithToken(route('invoice.payments.store', $invoice), [
        'amount' => 40, 'notes' => 'دفعة اختبار', 'paid_at' => now()->timezone('Africa/Cairo')->format('Y-m-d\TH:i'),
        'payment_invoice_id' => $invoice->id,
    ])->assertRedirect($profitabilityUrl)->assertSessionHasNoErrors()->assertSessionHas('success');
    $this->assertDatabaseHas('invoice_payments', ['invoice_id' => $invoice->id, 'amount' => 40, 'notes' => 'دفعة اختبار']);
    $this->get($profitabilityUrl)->assertViewHas('totalCustomerDebts', fn ($total) => (float) $total === 110.0);

    // سجّل زيارة فعلية بالهاتف نفسه وتأكد أنها تُنسب إلى عميل جديد.
    $this->consultationProduct();
    $this->postWithToken(route('customers.store'), [
        'name' => 'عميل جديد', 'phone' => $customer->phone, 'animal_type' => 'كلب', 'consultation_price' => 100,
    ])->assertRedirect(route('customers.index'))->assertSessionHasNoErrors();
    $newCustomer = Customer::where('phone', $customer->phone)->firstOrFail();
    expect($newCustomer->id)->not->toBe($customer->id);
    expect($invoice->fresh()->customer_id)->toBe($customer->id);
});

// تحقق من ظهور الحذف للموظف ورفض الرقم الخاطئ وقبول الرقم الصحيح.
it('allows employees to delete only after confirming the correct phone', function () {
    $customer = Customer::factory()->create(['phone' => '201012345678']);
    $invoice = Invoice::factory()->for($customer)->create();
    $this->actingAsEmployee();
    $this->get(route('customers.show', $customer))->assertOk()
        ->assertSeeText('مسح العميل وسجله بالكامل')
        ->assertSee('id="deleteCustomerHistoryModal"', false)
        ->assertSeeText('الفواتير التي ستُخفى: 1');

    $this->from(route('customers.show', $customer))->delete(route('customers.destroy', $customer), ['phone_confirmation' => '201000000000'])
        ->assertRedirect(route('customers.show', $customer))->assertSessionHasErrors(['phone_confirmation'], null, 'customerDeletion');
    expect($customer->fresh()->trashed())->toBeFalse()->and($invoice->fresh()->is_hidden)->toBeFalse();

    // تأكد من تنفيذ الحذف وإخفاء الفاتورة عند مطابقة الرقم.
    $this->delete(route('customers.destroy', $customer), ['phone_confirmation' => $customer->phone])
        ->assertRedirect(route('customers.index'))->assertSessionHas('success');
    $this->assertSoftDeleted($customer);
    expect($invoice->fresh()->is_hidden)->toBeTrue();
});

// تحقق من تحويل الضيف إلى تسجيل الدخول حتى إذا أرسل رقم العميل الصحيح.
it('requires login before deleting a customer with history', function () {
    $customer = Customer::factory()->create();
    $this->get(route('customers.show', $customer))->assertRedirect(route('login'));
    $this->delete(route('customers.destroy', $customer), ['phone_confirmation' => $customer->phone])
        ->assertRedirect(route('login'));
    expect($customer->fresh()->trashed())->toBeFalse();
});

// افحص الإخفاء صراحةً في قائمة الموظف وخط الزمن حتى لو كان العميل لا يزال نشطًا.
it('keeps hidden invoices out of employee search and the active medical timeline', function () {
    $this->actingAsEmployee();
    $customer = Customer::factory()->create();
    $animal = Animal::factory()->for($customer)->create();
    $visible = Invoice::factory()->for($customer)->for($animal)->create();
    $hidden = Invoice::factory()->for($customer)->for($animal)->create(['diagnosis' => 'تشخيص مخفي']);
    $orphan = Invoice::factory()->for($customer)->create();
    DB::table('invoices')->whereIn('id', [$hidden->id, $orphan->id])->update(['is_hidden' => true]);

    $this->get(route('customers.show', $customer))->assertOk()->assertSeeText($visible->invoice_number)
        ->assertDontSeeText($hidden->invoice_number)->assertDontSeeText($orphan->invoice_number);
    $this->get(route('animals.show', $animal))->assertOk()->assertDontSeeText($hidden->diagnosis);
    $this->get(route('invoices.index', ['period' => 'all']))->assertOk()->assertSeeText($visible->invoice_number)
        ->assertDontSeeText($hidden->invoice_number);
    $this->get(route('invoices.index', ['q' => $hidden->invoice_number, 'period' => 'all']))
        ->assertOk()->assertViewHas('invoices', fn ($results) => $results->isEmpty());
    $this->get(route('invoices.show', $hidden))->assertNotFound();
});
