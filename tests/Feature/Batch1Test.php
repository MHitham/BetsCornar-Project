<?php

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\InvoiceReturn;
use App\Models\Product;
use App\Services\CustomerVisitService;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// التحقق من أن الدفعة الصحيحة تنشئ صفاً واحداً وتزيد إجمالي الفاتورة.
test('addPayment creates one ledger row and increments amount paid', function () {
    $invoice = Invoice::factory()->create([
        'status' => 'confirmed',
        'total' => 100,
        'amount_paid' => 0,
    ]);

    $payment = app(InvoiceService::class)->addPayment($invoice, 40, 'دفعة اختبار');

    expect($payment)->toBeInstanceOf(InvoicePayment::class)
        ->and(InvoicePayment::count())->toBe(1)
        ->and((float) $invoice->fresh()->amount_paid)->toBe(40.0)
        ->and((float) $payment->amount)->toBe(40.0);
});

// التحقق من رفض الدفعة التي تتجاوز المتبقي دون إنشاء صف مالي.
test('addPayment rejects an amount greater than remaining', function () {
    $invoice = Invoice::factory()->create([
        'status' => 'confirmed',
        'total' => 100,
        'amount_paid' => 80,
    ]);

    expect(fn () => app(InvoiceService::class)->addPayment($invoice, 21))
        ->toThrow(RuntimeException::class);

    expect(InvoicePayment::count())->toBe(0)
        ->and((float) $invoice->fresh()->amount_paid)->toBe(80.0);
});

// التحقق من أن الفاتورة الملغية لا تقبل أي دفعة.
test('addPayment rejects a cancelled invoice', function () {
    $invoice = Invoice::factory()->create([
        'status' => 'cancelled',
        'total' => 100,
        'amount_paid' => 0,
    ]);

    expect(fn () => app(InvoiceService::class)->addPayment($invoice, 10))
        ->toThrow(RuntimeException::class);

    expect(InvoicePayment::count())->toBe(0)
        ->and((float) $invoice->fresh()->amount_paid)->toBe(0.0);
});

// التحقق من أن المرتجع النقدي يخفض amount_paid ولا يسمح بدفعة جديدة بعد اكتمال السداد.
test('addPayment rejects a new payment after a cash refund consumed the remaining balance', function () {
    $product = Product::factory()->product()->create(['quantity' => 5]);
    $invoice = app(InvoiceService::class)->saveQuickSale([
        'items' => [['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 200]],
    ]);

    app(InvoiceService::class)->createReturn($invoice->fresh(), [
        ['invoice_item_id' => $invoice->items->first()->id, 'quantity_returned' => 0.5],
    ]);

    expect((float) $invoice->fresh()->total)->toBe(300.0)
        ->and((float) $invoice->fresh()->amount_paid)->toBe(300.0)
        ->and((float) $invoice->fresh()->remaining_amount)->toBe(0.0);

    expect(fn () => app(InvoiceService::class)->addPayment($invoice->fresh(), 1))
        ->toThrow(RuntimeException::class);

    expect(InvoicePayment::count())->toBe(1)
        ->and((float) $invoice->fresh()->amount_paid)->toBe(300.0);
});

// التحقق من أن بند فاتورة أخرى يرفض المرتجع ولا يغير المخزون أو الإجماليات.
test('createReturn rejects an item from another invoice without changes', function () {
    $product = Product::factory()->product()->create(['quantity' => 10]);
    $target = app(InvoiceService::class)->saveQuickSale([
        'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100]],
    ]);
    $other = app(InvoiceService::class)->saveQuickSale([
        'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 50]],
    ]);
    $stockBefore = (float) $product->fresh()->quantity;
    $returnsBefore = InvoiceReturn::count();

    expect(fn () => app(InvoiceService::class)->createReturn($target->fresh(), [
        ['invoice_item_id' => $other->items->first()->id, 'quantity_returned' => 1],
    ]))->toThrow(RuntimeException::class, 'أحد البنود المرسلة لا يتبع هذه الفاتورة.');

    expect(InvoiceReturn::count())->toBe($returnsBefore)
        ->and((float) $target->fresh()->total)->toBe(100.0)
        ->and((float) $product->fresh()->quantity)->toBe($stockBefore);
});

// التحقق من حفظ متوسط تكلفة المنتج على بند البيع السريع.
test('quick sale stores cost price at sale', function () {
    $product = Product::factory()->product()->create([
        'quantity' => 5,
        'average_cost' => 27,
    ]);

    $invoice = app(InvoiceService::class)->saveQuickSale([
        'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 60]],
    ]);

    expect((float) $invoice->items->first()->fresh()->cost_price_at_sale)->toBe(27.0);
});

// التحقق من حفظ متوسط تكلفة المنتج على بند الزيارة العادي.
test('customer visit additional item stores cost price at sale', function () {
    $this->consultationProduct(100);
    $product = Product::factory()->product()->create([
        'quantity' => 5,
        'average_cost' => 15,
    ]);

    $invoice = app(CustomerVisitService::class)->saveVisit([
        'name' => 'Batch 1 Client',
        'phone' => '01055556666',
        'animal_type' => 'cat',
        'consultation_price' => 100,
        'additional_items' => [[
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 40,
        ]],
    ]);

    $item = $invoice->items()->where('product_id', $product->id)->first();

    expect((float) $item->fresh()->cost_price_at_sale)->toBe(15.0);
});

// التحقق من حذف نقطة الوصول التشخيصية العامة.
test('debug admin endpoint is removed', function () {
    $this->get('/debug-admin')->assertNotFound();
});
