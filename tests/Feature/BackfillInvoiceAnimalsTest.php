<?php

use App\Models\Animal;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Vaccination;

it('previews without changes and restores only reliable historical invoice links in bulk', function () {
    $customer = Customer::factory()->create();
    $rocky = Animal::factory()->for($customer)->create(['name' => 'روكي']);
    $hanafi = Animal::factory()->for($customer)->create(['name' => 'حنفي']);
    $otherCustomerAnimal = Animal::factory()->create(['name' => 'روكي']);
    $confirmed = Invoice::factory()->for($customer)->create(['source' => 'customer', 'total' => 150, 'amount_paid' => 100]);
    $otherConfirmed = Invoice::factory()->for($customer)->create(['source' => 'customer', 'animal_name' => 'حنفي']);
    $nameOnly = Invoice::factory()->for($customer)->create(['source' => 'customer', 'animal_name' => 'روكي']);
    $noChoice = Invoice::factory()->for($customer)->create(['source' => 'customer', 'diagnosis' => 'روكي']);
    $conflict = Invoice::factory()->for($customer)->create(['source' => 'customer']);
    $wrongOwner = Invoice::factory()->for($customer)->create(['source' => 'customer']);
    $alreadyLinked = Invoice::factory()->for($customer)->create(['source' => 'customer', 'animal_id' => $hanafi->id]);
    foreach ([[$confirmed, $rocky], [$otherConfirmed, $hanafi], [$conflict, $rocky], [$conflict, $hanafi],
        [$wrongOwner, $otherCustomerAnimal], [$alreadyLinked, $rocky]] as [$invoice, $animal]) {
        Vaccination::factory()->for($customer)->for($invoice)->for($animal)->create();
    }

    $this->artisan('invoices:backfill-animals')->expectsOutputToContain('المعاينة فقط')->assertSuccessful();
    expect($confirmed->fresh()->animal_id)->toBeNull()
        ->and($otherConfirmed->fresh()->animal_id)->toBeNull();

    $this->artisan('invoices:backfill-animals', ['--apply' => true])
        ->expectsConfirmation('بعد مراجعة الجدول وأخذ نسخة احتياطية، هل تؤكد ربط 2 فاتورة؟', 'yes')
        ->expectsOutput('تم ربط 2 فاتورة.')
        ->assertSuccessful();

    $confirmed->refresh();
    expect($confirmed->animal_id)->toBe($rocky->id)
        ->and($confirmed->animal_name)->toBe('روكي')
        ->and($confirmed->total)->toBe('150.00')
        ->and($confirmed->amount_paid)->toBe('100.00')
        ->and($otherConfirmed->fresh()->animal_id)->toBe($hanafi->id)
        ->and($alreadyLinked->fresh()->animal_id)->toBe($hanafi->id);
    foreach ([$nameOnly, $noChoice, $conflict, $wrongOwner] as $invoice) {
        expect($invoice->fresh()->animal_id)->toBeNull();
    }

    $this->artisan('invoices:backfill-animals', ['--apply' => true])
        ->expectsOutput('لا توجد فواتير جاهزة للربط.')
        ->assertSuccessful();
});

it('requires individual approval for an exact historical name and skips duplicate or newly reused names', function () {
    $customer = Customer::factory()->create();
    $rocky = Animal::factory()->for($customer)->create(['name' => 'روكي', 'created_at' => now()->subDays(3)]);
    Animal::factory()->count(2)->for($customer)->create(['name' => 'حنفي', 'created_at' => now()->subDays(3)]);
    Animal::factory()->for($customer)->create(['name' => 'سابق', 'created_at' => now()]);
    $invoiceData = ['customer_id' => $customer->id, 'source' => 'customer', 'created_at' => now()->subDay()];
    $reviewed = Invoice::factory()->create($invoiceData + ['animal_name' => 'روكي']);
    $duplicate = Invoice::factory()->create($invoiceData + ['animal_name' => 'حنفي']);
    $archived = Invoice::factory()->create($invoiceData + ['animal_name' => 'سابق']);

    $this->artisan('invoices:backfill-animals', ['--apply' => true, '--invoice' => null])->assertExitCode(2);

    foreach ([$duplicate, $archived] as $invoice) {
        $this->artisan('invoices:backfill-animals', ['--apply' => true, '--invoice' => $invoice->id])
            ->expectsOutput('لا توجد فواتير جاهزة للربط.')
            ->assertSuccessful();
        expect($invoice->fresh()->animal_id)->toBeNull();
    }

    $params = ['--apply' => true, '--invoice' => $reviewed->id];
    $question = 'بعد مراجعة الجدول وأخذ نسخة احتياطية، هل تؤكد ربط 1 فاتورة؟';
    $this->artisan('invoices:backfill-animals', $params)->expectsConfirmation($question, 'no')->assertSuccessful();
    expect($reviewed->fresh()->animal_id)->toBeNull();

    $this->artisan('invoices:backfill-animals', $params)->expectsConfirmation($question, 'yes')->assertSuccessful();
    expect($reviewed->fresh()->animal_id)->toBe($rocky->id);
});
