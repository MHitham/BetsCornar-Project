<?php

use App\Helpers\PhoneHelper;
use App\Models\Customer;
use App\Models\Product;
use App\Services\CustomerVisitService;

beforeEach(function () {
    $this->visitService = app(CustomerVisitService::class);

    // Consultation product for visits
    Product::factory()->service()->create([
        'name' => 'كشف',
        'price' => 100.0,
        'is_active' => true,
    ]);
});

test('phone helper normalizes various egyptian phone number formats into 201XXXXXXXXX', function () {
    $expected = '201012345678';

    expect(PhoneHelper::normalize('01012345678'))->toBe($expected)
        ->and(PhoneHelper::normalize('+20 101 234 5678'))->toBe($expected)
        ->and(PhoneHelper::normalize('010-1234-5678'))->toBe($expected)
        ->and(PhoneHelper::normalize('201012345678'))->toBe($expected)
        ->and(PhoneHelper::normalize('1012345678'))->toBe($expected)
        ->and(PhoneHelper::normalize('+20 (10) 1234-5678'))->toBe($expected);
});

test('creating a visit with a non-normalized phone number stores the normalized phone in customers table', function () {
    $rawPhone = '+20 109 876 5432';
    $expectedNormalized = '201098765432';

    $visitData = [
        'name' => 'ياسر ممدوح',
        'phone' => $rawPhone,
        'animal_type' => 'قط',
        'consultation_price' => 100.0,
    ];

    $invoice = $this->visitService->saveVisit($visitData);

    $customer = Customer::find($invoice->customer_id);

    expect($customer)->not->toBeNull()
        ->and($customer->phone)->toBe($expectedNormalized);

    expect(Customer::where('phone', $rawPhone)->exists())->toBeFalse()
        ->and(Customer::where('phone', $expectedNormalized)->count())->toBe(1);
});

test('submitting a second visit with the same phone in a different raw format reuses the same customer row', function () {
    $firstRawPhone = '01012345678';
    $secondRawPhone = '+20 101 234 5678';
    $thirdRawPhone = '010-1234-5678';
    $expectedNormalized = '201012345678';

    // First visit
    $invoice1 = $this->visitService->saveVisit([
        'name' => 'أحمد محمود',
        'phone' => $firstRawPhone,
        'animal_type' => 'كلب',
        'consultation_price' => 100.0,
    ]);

    $customerId = $invoice1->customer_id;
    expect(Customer::count())->toBe(1);

    // Second visit with different format
    $invoice2 = $this->visitService->saveVisit([
        'name' => 'أحمد محمود',
        'phone' => $secondRawPhone,
        'animal_type' => 'كلب',
        'consultation_price' => 100.0,
    ]);

    expect($invoice2->customer_id)->toBe($customerId)
        ->and(Customer::count())->toBe(1);

    // Third visit with yet another format
    $invoice3 = $this->visitService->saveVisit([
        'name' => 'أحمد محمود',
        'phone' => $thirdRawPhone,
        'animal_type' => 'كلب',
        'consultation_price' => 100.0,
    ]);

    expect($invoice3->customer_id)->toBe($customerId)
        ->and(Customer::count())->toBe(1);

    $customer = Customer::find($customerId);
    expect($customer->phone)->toBe($expectedNormalized);
});

test('customers phone unique constraint is never violated across visits with different phone formats', function () {
    $formats = [
        '01123456789',
        '+20 112 345 6789',
        '201123456789',
        '011-2345-6789',
    ];

    $customerIds = [];

    foreach ($formats as $phone) {
        $invoice = $this->visitService->saveVisit([
            'name' => 'عميل مكرر الفرمتة',
            'phone' => $phone,
            'animal_type' => 'قط',
            'consultation_price' => 100.0,
        ]);

        $customerIds[] = $invoice->customer_id;
    }

    // All visits must map to exactly one single customer ID
    expect(array_unique($customerIds))->toHaveCount(1)
        ->and(Customer::where('phone', '201123456789')->count())->toBe(1);
});
