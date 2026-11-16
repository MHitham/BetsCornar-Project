<?php

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceItemVaccineBatch;
use App\Models\Product;
use App\Models\VaccineBatch;
use App\Services\StockService;

beforeEach(function () {
    $this->stockService = app(StockService::class);
});

test('deducting a vaccine quantity fully covered by the earliest-expiry batch only deducts from that batch', function () {
    $product = Product::factory()->vaccination()->create();

    $earliestBatch = VaccineBatch::factory()->create([
        'product_id' => $product->id,
        'received_date' => now()->subDays(10)->toDateString(),
        'expiry_date' => now()->addDays(30)->toDateString(),
        'quantity_received' => 10,
        'quantity_remaining' => 10,
    ]);

    $laterBatch = VaccineBatch::factory()->create([
        'product_id' => $product->id,
        'received_date' => now()->subDays(5)->toDateString(),
        'expiry_date' => now()->addDays(60)->toDateString(),
        'quantity_received' => 10,
        'quantity_remaining' => 10,
    ]);

    $this->stockService->recalculateVaccineStock($product);
    expect((float) $product->fresh()->quantity)->toBe(20.0);

    $deductions = $this->stockService->deductVaccineStockFefo($product, 4.0);

    expect($deductions)->toHaveCount(1)
        ->and($deductions[0]['vaccine_batch_id'])->toBe($earliestBatch->id)
        ->and((float) $deductions[0]['quantity'])->toBe(4.0);

    expect((float) $earliestBatch->fresh()->quantity_remaining)->toBe(6.0)
        ->and((float) $laterBatch->fresh()->quantity_remaining)->toBe(10.0)
        ->and((float) $product->fresh()->quantity)->toBe(16.0);
});

test('deducting a quantity larger than the earliest batch splits deduction across multiple batches in expiry-ascending order creating correct invoice_item_vaccine_batches rows', function () {
    $product = Product::factory()->vaccination()->create();

    $invoice = Invoice::factory()->create();
    $invoiceItem = InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'product_id' => $product->id,
        'quantity' => 7.0,
        'unit_price' => 100,
        'line_total' => 700,
    ]);

    $batch1 = VaccineBatch::factory()->create([
        'product_id' => $product->id,
        'expiry_date' => now()->addDays(10)->toDateString(),
        'quantity_received' => 4,
        'quantity_remaining' => 4,
    ]);

    $batch2 = VaccineBatch::factory()->create([
        'product_id' => $product->id,
        'expiry_date' => now()->addDays(20)->toDateString(),
        'quantity_received' => 6,
        'quantity_remaining' => 6,
    ]);

    $batch3 = VaccineBatch::factory()->create([
        'product_id' => $product->id,
        'expiry_date' => now()->addDays(30)->toDateString(),
        'quantity_received' => 10,
        'quantity_remaining' => 10,
    ]);

    $this->stockService->recalculateVaccineStock($product);

    $deductions = $this->stockService->deductVaccineStockFefo($product, 7.0, $invoiceItem);

    expect($deductions)->toHaveCount(2)
        ->and($deductions[0]['vaccine_batch_id'])->toBe($batch1->id)
        ->and((float) $deductions[0]['quantity'])->toBe(4.0)
        ->and($deductions[1]['vaccine_batch_id'])->toBe($batch2->id)
        ->and((float) $deductions[1]['quantity'])->toBe(3.0);

    expect((float) $batch1->fresh()->quantity_remaining)->toBe(0.0)
        ->and((float) $batch2->fresh()->quantity_remaining)->toBe(3.0)
        ->and((float) $batch3->fresh()->quantity_remaining)->toBe(10.0);

    $pivotRows = InvoiceItemVaccineBatch::where('invoice_item_id', $invoiceItem->id)
        ->orderBy('vaccine_batch_id')
        ->get();

    expect($pivotRows)->toHaveCount(2)
        ->and($pivotRows[0]->vaccine_batch_id)->toBe($batch1->id)
        ->and((float) $pivotRows[0]->quantity)->toBe(4.0)
        ->and($pivotRows[1]->vaccine_batch_id)->toBe($batch2->id)
        ->and((float) $pivotRows[1]->quantity)->toBe(3.0);

    expect(InvoiceItemVaccineBatch::where('vaccine_batch_id', $batch3->id)->exists())->toBeFalse();
});

test('expired batches are never selected for deduction even if quantity_remaining is greater than zero', function () {
    $product = Product::factory()->vaccination()->create();

    $expiredBatch = VaccineBatch::factory()->create([
        'product_id' => $product->id,
        'expiry_date' => now()->subDay()->toDateString(),
        'quantity_received' => 10,
        'quantity_remaining' => 10,
    ]);

    $validBatch = VaccineBatch::factory()->create([
        'product_id' => $product->id,
        'expiry_date' => now()->addDays(15)->toDateString(),
        'quantity_received' => 5,
        'quantity_remaining' => 5,
    ]);

    $this->stockService->recalculateVaccineStock($product);
    // Product quantity should only count non-expired batches
    expect((float) $product->fresh()->quantity)->toBe(5.0);

    $deductions = $this->stockService->deductVaccineStockFefo($product, 3.0);

    expect($deductions)->toHaveCount(1)
        ->and($deductions[0]['vaccine_batch_id'])->toBe($validBatch->id)
        ->and((float) $deductions[0]['quantity'])->toBe(3.0);

    expect((float) $expiredBatch->fresh()->quantity_remaining)->toBe(10.0)
        ->and((float) $validBatch->fresh()->quantity_remaining)->toBe(2.0);
});

test('when total usable quantity is less than requested the entire operation is blocked and no changes are committed', function () {
    $product = Product::factory()->vaccination()->create();

    $expiredBatch = VaccineBatch::factory()->create([
        'product_id' => $product->id,
        'expiry_date' => now()->subDays(5)->toDateString(),
        'quantity_received' => 20,
        'quantity_remaining' => 20,
    ]);

    $validBatch = VaccineBatch::factory()->create([
        'product_id' => $product->id,
        'expiry_date' => now()->addDays(5)->toDateString(),
        'quantity_received' => 2,
        'quantity_remaining' => 2,
    ]);

    $this->stockService->recalculateVaccineStock($product);

    $initialBatchRemaining = (float) $validBatch->fresh()->quantity_remaining;
    $initialProductQty = (float) $product->fresh()->quantity;
    $initialPivotCount = InvoiceItemVaccineBatch::count();

    $invoice = Invoice::factory()->create();
    $invoiceItem = InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'product_id' => $product->id,
        'quantity' => 5.0,
    ]);

    expect(function () use ($product, $invoiceItem) {
        $this->stockService->deductVaccineStockFefo($product, 5.0, $invoiceItem);
    })->toThrow(RuntimeException::class);

    // Verify row counts and quantities before and after are unchanged
    expect((float) $validBatch->fresh()->quantity_remaining)->toBe($initialBatchRemaining)
        ->and((float) $expiredBatch->fresh()->quantity_remaining)->toBe(20.0)
        ->and((float) $product->fresh()->quantity)->toBe($initialProductQty)
        ->and(InvoiceItemVaccineBatch::count())->toBe($initialPivotCount);
});

test('after a successful deduction products quantity recalculates as the sum of quantity_remaining across only non-expired batches', function () {
    $product = Product::factory()->vaccination()->create();

    $expiredBatch = VaccineBatch::factory()->create([
        'product_id' => $product->id,
        'expiry_date' => now()->subDays(2)->toDateString(),
        'quantity_received' => 10,
        'quantity_remaining' => 10,
    ]);

    $batch1 = VaccineBatch::factory()->create([
        'product_id' => $product->id,
        'expiry_date' => now()->addDays(10)->toDateString(),
        'quantity_received' => 8,
        'quantity_remaining' => 8,
    ]);

    $batch2 = VaccineBatch::factory()->create([
        'product_id' => $product->id,
        'expiry_date' => now()->addDays(20)->toDateString(),
        'quantity_received' => 5,
        'quantity_remaining' => 5,
    ]);

    $this->stockService->recalculateVaccineStock($product);
    expect((float) $product->fresh()->quantity)->toBe(13.0);

    $this->stockService->deductVaccineStockFefo($product, 5.0);

    // batch1 has 3 left, batch2 has 5 left, expired has 10 left (ignored)
    // 3 + 5 = 8
    expect((float) $batch1->fresh()->quantity_remaining)->toBe(3.0)
        ->and((float) $batch2->fresh()->quantity_remaining)->toBe(5.0)
        ->and((float) $expiredBatch->fresh()->quantity_remaining)->toBe(10.0)
        ->and((float) $product->fresh()->quantity)->toBe(8.0);
});
