<?php

// اختبارات FIX-2: استعادة مخزون اللقاح — تضمن دقة المرتجعات الجزئية والإلغاء وسلامة البيانات القديمة.

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceItemVaccineBatch;
use App\Models\Product;
use App\Models\VaccineBatch;
use App\Services\InvoiceService;
use Illuminate\Support\Facades\Log;

beforeEach(function (): void {
    $this->actingAsAdmin();
    $this->service = app(InvoiceService::class);
});

// ============================================================
// حالة 1: بيع 4 → مرتجع 1 → إلغاء → batch يعود لـ 10 بالضبط
// ============================================================
test('sell 4 return 1 then cancel restores batch to exactly 10', function (): void {
    $product = Product::factory()->vaccination()->create();
    $batch = VaccineBatch::factory()->create([
        'product_id' => $product->id,
        'quantity_received' => 10,
        'quantity_remaining' => 10,
    ]);

    // بيع 4 → remaining = 6
    $invoice = $this->service->saveQuickSale([
        'items' => [['product_id' => $product->id, 'quantity' => 4, 'unit_price' => 100]],
    ]);
    expect((float) $batch->fresh()->quantity_remaining)->toBe(6.0);

    // مرتجع 1 → remaining = 7، pivot = 3
    $item = $invoice->items->first();
    $this->service->createReturn($invoice->fresh(), [
        ['invoice_item_id' => $item->id, 'quantity_returned' => 1],
    ]);
    expect((float) $batch->fresh()->quantity_remaining)->toBe(7.0);

    // التحقق أن الـ pivot تحدّث إلى 3 (وليس 4)
    $pivot = InvoiceItemVaccineBatch::where('invoice_item_id', $item->id)->first();
    expect((float) $pivot->quantity)->toBe(3.0, 'الـ pivot يجب أن يصبح 3 بعد مرتجع 1 من 4');

    // إلغاء الفاتورة → remaining يجب أن يعود لـ 10 بالضبط (وليس 11)
    $this->service->cancelInvoice($invoice->fresh(), 'test cancel');
    expect((float) $batch->fresh()->quantity_remaining)->toBe(10.0, 'batch يجب أن يعود لـ 10 بعد الإلغاء');
});

// ============================================================
// حالة 2: مرتجعان متسلسلان (1 ثم 1 من 4) → batch = 8.0 بالضبط
// ============================================================
test('two sequential partial returns of 1 each from 4 sold restore to exactly 8', function (): void {
    $product = Product::factory()->vaccination()->create();
    $batch = VaccineBatch::factory()->create([
        'product_id' => $product->id,
        'quantity_received' => 10,
        'quantity_remaining' => 10,
    ]);

    $invoice = $this->service->saveQuickSale([
        'items' => [['product_id' => $product->id, 'quantity' => 4, 'unit_price' => 100]],
    ]);
    $item = $invoice->items->first();

    // مرتجع أول: 1 من 4 → remaining = 7، pivot = 3
    $this->service->createReturn($invoice->fresh(), [
        ['invoice_item_id' => $item->id, 'quantity_returned' => 1],
    ]);
    expect((float) $batch->fresh()->quantity_remaining)->toBe(7.0);

    // مرتجع ثاني: 1 من 3 → remaining = 8 (وليس 8.33)
    $this->service->createReturn($invoice->fresh(), [
        ['invoice_item_id' => $item->id, 'quantity_returned' => 1],
    ]);
    expect((float) $batch->fresh()->quantity_remaining)->toBe(8.0, 'يجب أن يكون 8.0 وليس 8.33');
});

// ============================================================
// حالة 3: مرتجع يمتد على أكثر من batch (FEFO)
// ============================================================
test('partial return across multiple batches restores proportionally', function (): void {
    $product = Product::factory()->vaccination()->create();

    // batch A: 6 doses (ينتهي أولًا بـ FEFO)
    $batchA = VaccineBatch::factory()->create([
        'product_id' => $product->id,
        'quantity_received' => 6,
        'quantity_remaining' => 6,
        'expiry_date' => now()->addMonths(2)->toDateString(),
    ]);

    // batch B: 4 doses
    $batchB = VaccineBatch::factory()->create([
        'product_id' => $product->id,
        'quantity_received' => 4,
        'quantity_remaining' => 4,
        'expiry_date' => now()->addMonths(6)->toDateString(),
    ]);

    // بيع 8: يأخذ 6 من A و 2 من B
    $invoice = $this->service->saveQuickSale([
        'items' => [['product_id' => $product->id, 'quantity' => 8, 'unit_price' => 100]],
    ]);

    expect((float) $batchA->fresh()->quantity_remaining)->toBe(0.0);
    expect((float) $batchB->fresh()->quantity_remaining)->toBe(2.0);

    $item = $invoice->items->first();

    // مرتجع 4 (نصف ما بيع): A يسترد 3، B يسترد 1
    $this->service->createReturn($invoice->fresh(), [
        ['invoice_item_id' => $item->id, 'quantity_returned' => 4],
    ]);

    expect((float) $batchA->fresh()->quantity_remaining)->toBe(3.0, 'batch A يجب أن يسترد 3');
    expect((float) $batchB->fresh()->quantity_remaining)->toBe(3.0, 'batch B يجب أن يسترد 1 → 2+1=3');
});

// ============================================================
// حالة 4: Rounding — 3 doses موزعة على 2 batches، مرتجع 1
// ============================================================
test('rounding case 3 doses over 2 batches return 1 is exact', function (): void {
    $product = Product::factory()->vaccination()->create();

    // batch A: 2 doses فقط (ينتهي أولًا بـ FEFO) → سيأخذ FEFO كل الـ 2 منه
    $batchA = VaccineBatch::factory()->create([
        'product_id' => $product->id,
        'quantity_received' => 2,
        'quantity_remaining' => 2,
        'expiry_date' => now()->addMonths(1)->toDateString(),
    ]);

    // batch B: 10 doses → سيأخذ FEFO 1 منه (3 - 2 = 1)
    $batchB = VaccineBatch::factory()->create([
        'product_id' => $product->id,
        'quantity_received' => 10,
        'quantity_remaining' => 10,
        'expiry_date' => now()->addMonths(5)->toDateString(),
    ]);

    // بيع 3: يأخذ 2 من A، 1 من B (FEFO)
    $invoice = $this->service->saveQuickSale([
        'items' => [['product_id' => $product->id, 'quantity' => 3, 'unit_price' => 100]],
    ]);

    expect((float) $batchA->fresh()->quantity_remaining)->toBe(0.0, 'batch A يُفرغ تمامًا');
    expect((float) $batchB->fresh()->quantity_remaining)->toBe(9.0, 'batch B يبقى بـ 9');

    $item = $invoice->items->first();

    // مرتجع 1 من 3: ratio = 1/3
    // A: pivot=2 × (1/3) = 0.67، B (آخر batch): 1 - 0.67 = 0.33
    // المجموع = 1.00 بالضبط ✓
    $this->service->createReturn($invoice->fresh(), [
        ['invoice_item_id' => $item->id, 'quantity_returned' => 1],
    ]);

    $remainingA = (float) $batchA->fresh()->quantity_remaining;
    $remainingB = (float) $batchB->fresh()->quantity_remaining;

    // A: 0 + 0.67 = 0.67، B: 9 + 0.33 = 9.33 → المجموع = 10.00
    // أو A آخر batch: B = 9 + 0.67 = 9.67، A = 0 + 0.33 = 0.33 → حسب ترتيب FEFO
    // الأهم: المجموع = (0+9) + 1 = 10 بالضبط
    expect($remainingA + $remainingB)->toBe(10.0, 'المجموع الكلي يجب أن يساوي 10 بعد المرتجع');
    // لا يوجد overflow
    expect($remainingA)->toBeLessThanOrEqual((float) $batchA->quantity_received);
    expect($remainingB)->toBeLessThanOrEqual((float) $batchB->quantity_received);
});

// ============================================================
// حالة 5: بيانات قديمة — pivot=4، item.quantity=3 (مرتجع سابق لم يحدّث pivot)
// الإلغاء يجب أن يسترد 3 فقط (وليس 4)
// ============================================================
test('old data safety cancel restores current item quantity not stale pivot', function (): void {
    $product = Product::factory()->vaccination()->create();
    $batch = VaccineBatch::factory()->create([
        'product_id' => $product->id,
        'quantity_received' => 10,
        'quantity_remaining' => 6, // بعد البيع: 10 - 4 = 6
    ]);

    // ننشئ invoice item وpivot يدويًا بالبيانات القديمة (pivot=4، item.quantity=3)
    $invoice = Invoice::factory()->create(['status' => 'confirmed', 'total' => 300, 'amount_paid' => 300]);
    $item = InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'product_id' => $product->id,
        'quantity' => 3, // بعد مرتجع سابق لم يحدّث pivot
        'unit_price' => 100,
        'line_total' => 300,
    ]);

    // pivot قديم: quantity=4 (لم يتحدث بعد مرتجع 1)
    InvoiceItemVaccineBatch::create([
        'invoice_item_id' => $item->id,
        'vaccine_batch_id' => $batch->id,
        'quantity' => 4,
    ]);

    // إلغاء الفاتورة: scale = 3/4 = 0.75، toRestore = 4 * 0.75 = 3
    app(InvoiceService::class)->cancelInvoice($invoice, 'old data test');

    // remaining = 6 + 3 = 9 (وليس 6 + 4 = 10 — البيانات القديمة كانت ستعطي 10 لكن هذا مقبول هنا لأن remaining=6 تعكس المرتجع)
    // الأهم: لا يتجاوز 10
    expect((float) $batch->fresh()->quantity_remaining)->toBeLessThanOrEqual(10.0, 'لا يجب أن يتجاوز quantity_received');
    expect((float) $batch->fresh()->quantity_remaining)->toBe(9.0, 'scale 0.75 × 4 = 3 → 6+3 = 9');
});

// ============================================================
// حالة 6: الحارس — محاولة استعادة تتجاوز quantity_received تُلقي exception وتُلغي Transaction
// ============================================================
test('guard throws exception and rolls back when restore would exceed quantity_received', function (): void {
    Log::spy();

    $product = Product::factory()->vaccination()->create();
    $batch = VaccineBatch::factory()->create([
        'product_id' => $product->id,
        'quantity_received' => 10,
        'quantity_remaining' => 10, // ممتلئ بالفعل — أي إضافة ستتجاوز الحد
    ]);

    // ننشئ invoice item وpivot يدويًا بقيمة تؤدي لـ overflow عند الاستعادة
    $invoice = Invoice::factory()->create(['status' => 'confirmed', 'total' => 100, 'amount_paid' => 100]);
    $item = InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => 50,
        'line_total' => 100,
    ]);
    InvoiceItemVaccineBatch::create([
        'invoice_item_id' => $item->id,
        'vaccine_batch_id' => $batch->id,
        'quantity' => 2,
    ]);

    // هذا سيحاول إضافة 2 على 10 (الحد المسموح) → overflow
    expect(fn () => app(InvoiceService::class)->cancelInvoice($invoice, 'overflow test'))
        ->toThrow(RuntimeException::class);

    // التحقق أن الـ remaining لم يتغير (rollback نجح)
    expect((float) $batch->fresh()->quantity_remaining)->toBe(10.0, 'الـ rollback يجب أن يُلغي أي تغيير');

    // Log::error يجب أن يكون استُدعي
    Log::shouldHaveReceived('error')->once();
});

// ============================================================
// حالة 7: منتج عادي (غير لقاح) لا يتأثر بأي من التغييرات
// ============================================================
test('non-vaccine product stock is unaffected by vaccine restore changes', function (): void {
    $product = Product::factory()->product()->create(['quantity' => 10]);

    $invoice = $this->service->saveQuickSale([
        'items' => [['product_id' => $product->id, 'quantity' => 3, 'unit_price' => 50]],
    ]);

    expect((float) $product->fresh()->quantity)->toBe(7.0);

    $item = $invoice->items->first();
    $this->service->createReturn($invoice->fresh(), [
        ['invoice_item_id' => $item->id, 'quantity_returned' => 1],
    ]);
    expect((float) $product->fresh()->quantity)->toBe(8.0);

    $this->service->cancelInvoice($invoice->fresh(), 'non-vaccine test');
    expect((float) $product->fresh()->quantity)->toBe(10.0);
});
