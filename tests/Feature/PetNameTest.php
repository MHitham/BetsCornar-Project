<?php

use App\Models\Animal;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Vaccination;
use App\Services\CustomerVisitService;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->consultationProduct = Product::factory()->service()->create([
        'name' => 'كشف',
        'is_active' => true,
    ]);
});

test('a visit without an animal is rejected when the customer has multiple active animals', function () {
    $customer = Customer::factory()->create(['phone' => '201011112222']);
    Animal::factory()->create(['customer_id' => $customer->id, 'is_active' => true]);
    Animal::factory()->create(['customer_id' => $customer->id, 'is_active' => true]);
    $invoiceCount = Invoice::count();

    expect(fn () => app(CustomerVisitService::class)->saveVisit([
        'name' => $customer->name,
        'phone' => '01011112222',
        'animal_type' => 'قط',
        'consultation_price' => 0,
    ]))->toThrow(ValidationException::class);

    expect(Invoice::count())->toBe($invoiceCount)
        ->and(Customer::whereKey($customer->id)->exists())->toBeTrue();
});

test('a visit without an animal remains allowed for a customer with one animal', function () {
    $customer = Customer::factory()->create(['phone' => '201022223333']);
    Animal::factory()->create(['customer_id' => $customer->id, 'is_active' => true]);

    $invoice = app(CustomerVisitService::class)->saveVisit([
        'name' => $customer->name,
        'phone' => '01022223333',
        'animal_type' => 'قط',
        'consultation_price' => 0,
    ]);

    expect($invoice->animal_id)->toBeNull()
        ->and($invoice->animal_name)->toBeNull();
});

test('a new inline animal is copied to the invoice animal_name', function () {
    $invoice = app(CustomerVisitService::class)->saveVisit([
        'name' => 'عميل حيوان جديد',
        'phone' => '01033334444',
        'animal_type' => 'قط',
        'animal_id' => 'new_animal',
        'new_animal' => [
            'name' => 'لولو',
            'species' => 'cat',
        ],
        'consultation_price' => 0,
    ]);

    expect($invoice->animal_name)->toBe('لولو')
        ->and($invoice->pet_name)->toBe('لولو');
});

test('invoice and vaccination pages expose the pet name without null in WhatsApp URLs', function () {
    $this->actingAsAdmin();
    $customer = Customer::factory()->create(['name' => 'مالك لولو']);
    $animal = Animal::factory()->create(['customer_id' => $customer->id, 'name' => 'لولو']);
    $vaccine = Product::factory()->vaccination()->create(['name' => 'لقاح تجريبي']);
    $invoice = Invoice::create([
        'invoice_number' => 'INV-PET-'.fake()->unique()->numberBetween(1000, 9999),
        'customer_id' => $customer->id,
        'animal_id' => $animal->id,
        'animal_name' => 'لولو',
        'customer_name' => $customer->name,
        'source' => 'customer',
        'total' => 0,
        'amount_paid' => 0,
        'status' => 'confirmed',
    ]);
    Vaccination::create([
        'customer_id' => $customer->id,
        'animal_id' => $animal->id,
        'product_id' => $vaccine->id,
        'invoice_id' => $invoice->id,
        'vaccination_date' => today(),
        'next_dose_date' => today()->addDays(20),
    ]);

    $this->get(route('invoices.show', $invoice))->assertSee('الحيوان: لولو');
    $response = $this->get(route('vaccinations.index', ['q' => $customer->name, 'is_completed' => '0']));
    $response->assertSee('لولو')->assertSee('wa.me');
    expect(urldecode((string) $response->getContent()))->toContain('لولو');
});

test('invoice pdf remains one page and includes the pet name', function () {
    $this->actingAsAdmin();
    $customer = Customer::factory()->create();
    $animal = Animal::factory()->create(['customer_id' => $customer->id, 'name' => 'بيلا']);
    $invoice = Invoice::create([
        'invoice_number' => 'INV-PDF-'.fake()->unique()->numberBetween(1000, 9999),
        'customer_id' => $customer->id,
        'animal_id' => $animal->id,
        'animal_name' => 'بيلا',
        'customer_name' => $customer->name,
        'source' => 'customer',
        'total' => 0,
        'amount_paid' => 0,
        'status' => 'confirmed',
    ]);

    $response = $this->get(route('invoices.pdf', $invoice));
    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($response->getContent())->toContain('%PDF')
        ->and(preg_match_all('/\/Type\s*\/Page[^s]/', $response->getContent()))->toBe(1);
});

test('vaccination WhatsApp text omits null and empty animal brackets for legacy rows', function () {
    $this->actingAsAdmin();
    $customer = Customer::factory()->create(['name' => 'عميل بدون حيوان']);
    $vaccine = Product::factory()->vaccination()->create(['name' => 'لقاح قديم']);
    $invoice = Invoice::create([
        'invoice_number' => 'INV-ORPHAN-'.fake()->unique()->numberBetween(1000, 9999),
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'source' => 'customer',
        'total' => 0,
        'amount_paid' => 0,
        'status' => 'confirmed',
    ]);
    Vaccination::create([
        'customer_id' => $customer->id,
        'product_id' => $vaccine->id,
        'invoice_id' => $invoice->id,
        'vaccination_date' => today(),
        'next_dose_date' => today()->addDays(20),
    ]);

    $html = (string) $this->get(route('vaccinations.index', [
        'q' => $customer->name,
        'is_completed' => '0',
    ]))->getContent();
    preg_match_all('/href="(https:\/\/wa\.me[^"]+)"/', $html, $matches);
    $whatsappTexts = array_map('urldecode', $matches[1]);

    expect($whatsappTexts)->not->toBeEmpty()
        ->and(implode("\n", $whatsappTexts))->not->toContain('null')
        ->and(implode("\n", $whatsappTexts))->not->toContain('الحيوان ()');
});
