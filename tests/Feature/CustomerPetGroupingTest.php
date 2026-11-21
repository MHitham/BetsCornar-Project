<?php

use App\Models\Animal;
use App\Models\Customer;
use App\Models\Invoice;
// أنشئ بنودًا تجريبية لفحص الأسماء وعدد الاستعلامات.
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Vaccination;
use Illuminate\Support\Facades\DB;

function makeGroupedCustomer(int $animals): Customer
{
    $customer = Customer::factory()->create();
    for ($index = 1; $index <= $animals; $index++) {
        $animal = Animal::factory()->create([
            'customer_id' => $customer->id,
            'name' => "Pet {$index}",
            'species' => 'cat',
        ]);
        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'animal_id' => $animal->id,
            'customer_name' => $customer->name,
            'invoice_number' => "GROUP-{$animals}-{$index}",
            'diagnosis' => "Diagnosis {$index}",
        ]);
        $vaccine = Product::factory()->vaccination()->create(['name' => "Vaccine {$index}"]);
        Vaccination::create([
            'customer_id' => $customer->id,
            'animal_id' => $animal->id,
            'product_id' => $vaccine->id,
            'invoice_id' => $invoice->id,
            'vaccination_date' => today(),
            'next_dose_date' => today()->addDays(30),
        ]);
    }

    return $customer;
}

test('customer file groups each invoice and vaccination under its own animal', function () {
    $this->actingAsAdmin();
    $customer = makeGroupedCustomer(2);

    $response = $this->get(route('customers.show', $customer));

    $response->assertSuccessful()
        ->assertSee('data-pet-name="Pet 1"', false)
        ->assertSee('data-pet-name="Pet 2"', false)
        ->assertSee('GROUP-2-1')
        ->assertSee('GROUP-2-2')
        ->assertSee('Vaccine 1')
        ->assertSee('Vaccine 2');

    $orphan = Invoice::factory()->create([
        'customer_id' => $customer->id,
        'animal_id' => null,
        'customer_name' => $customer->name,
        'invoice_number' => 'GROUP-ORPHAN',
    ]);
    $orphanResponse = $this->get(route('customers.show', $customer));
    // يبقى قسم السجلات القديمة في الأرشيف وتظهر فيه تسمية الأصناف.
    $orphanResponse->assertSee('أرشيف')->assertSee('GROUP-ORPHAN')
        ->assertDontSeeText('سجلات بدون حيوان')
        ->assertSeeText('الأصناف');
    expect($orphan->fresh()->animal_id)->toBeNull();
});

test('customer file query count does not grow with the number of animals', function () {
    $this->actingAsAdmin();
    $one = makeGroupedCustomer(1);
    $this->get(route('customers.show', $one));
    DB::enableQueryLog();
    DB::flushQueryLog();
    $this->get(route('customers.show', $one));
    $oneQueries = count(DB::getQueryLog());
    DB::disableQueryLog();

    $four = makeGroupedCustomer(4);
    $this->get(route('customers.show', $four));
    DB::enableQueryLog();
    DB::flushQueryLog();
    $this->get(route('customers.show', $four));
    $fourQueries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($fourQueries)->toBe($oneQueries);
});

// افحص الأسماء المعروضة وعدّاد الزيادة والحالة الفارغة وصنف الأرشيف بصلاحية الموظف.
test('medical timeline shows only distinct item name badges with a five-name limit', function () {
    $this->actingAsEmployee();
    $customer = Customer::factory()->create();
    $animal = Animal::factory()->for($customer)->create();

    foreach ([1, 3, 7, 0, 2] as $order => $size) {
        $invoice = Invoice::factory()->for($customer)->create([
            'animal_id' => $order === 4 ? null : $animal->id,
            'created_at' => now()->subMinutes($order),
        ]);
        for ($index = 1; $index <= $size; $index++) {
            $product = Product::factory()->create(['name' => "صنف {$size}-{$index}"]);
            $attributes = [
                'quantity' => 12.34, 'unit_price' => 42.67, 'line_total' => 526.15, 'cost_price_at_sale' => 13.79,
            ];
            InvoiceItem::factory()->for($invoice)->for($product)->create($attributes);
            if ($size === 7 && $index === 1) {
                InvoiceItem::factory()->for($invoice)->for($product)->create($attributes);
            }
        }
    }

    $response = $this->get(route('customers.show', $customer))->assertOk();
    preg_match_all('/<div class="small" data-invoice-item-names>.*?<\/div>\s*<\/div>/s', $response->getContent(), $sections);
    expect($sections[0])->toHaveCount(5);
    $badges = array_map(function (string $html): array {
        preg_match_all('/<span class="badge bg-light text-dark border">(.*?)<\/span>/s', $html, $matches);

        return array_map(fn ($badge) => trim(strip_tags($badge)), $matches[1]);
    }, $sections[0]);

    expect($badges)->toBe([
        ['صنف 1-1'],
        ['صنف 3-1', 'صنف 3-2', 'صنف 3-3'],
        ['صنف 7-1', 'صنف 7-2', 'صنف 7-3', 'صنف 7-4', 'صنف 7-5', '+2 أخرى'],
        [],
        ['صنف 2-1', 'صنف 2-2'],
    ]);
    expect($sections[0][3])->toContain('<span>—</span>');
    expect(implode('', $sections[0]))->not->toContain('12.34', '42.67', '526.15', '13.79');
});

// قارن طلبين للعميل نفسه للتأكد من ثبات الاستعلامات عند زيادة الفواتير في المجموعتين.
test('medical timeline query count stays constant from two to twenty invoices with items', function () {
    $this->actingAsEmployee();
    $customer = Customer::factory()->create();
    $animal = Animal::factory()->for($customer)->create();
    $product = Product::factory()->create();
    $url = route('customers.show', $customer);
    $addInvoices = function (int $perGroup) use ($customer, $animal, $product): void {
        foreach ([$animal->id, null] as $animalId) {
            for ($index = 0; $index < $perGroup; $index++) {
                $invoice = Invoice::factory()->for($customer)->create(['animal_id' => $animalId]);
                InvoiceItem::factory()->for($invoice)->for($product)->create();
            }
        }
    };
    $queriesForPage = function () use ($url): array {
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $this->get($url)->assertOk();

            return array_column(DB::getQueryLog(), 'query');
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    };

    $addInvoices(1);
    $this->get($url)->assertOk();
    $twoInvoices = $queriesForPage();
    $addInvoices(9);
    $twentyInvoices = $queriesForPage();

    expect($twentyInvoices)->toHaveCount(count($twoInvoices));
    expect(array_filter($twentyInvoices, fn ($sql) => str_contains(str_replace(['"', '`'], '', $sql), 'from invoice_items')))
        ->toHaveCount(2);
});
