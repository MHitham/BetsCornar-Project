<?php

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceItemVaccineBatch;
use App\Models\Product;
use App\Models\VaccineBatch;
use App\Services\InvoiceService;
use App\Services\StockService;

beforeEach(function () {
    $this->invoiceService = app(InvoiceService::class);
    $this->stockService = app(StockService::class);
});

test('cancelling a confirmed invoice with non-vaccine tracked items increases quantity back and updates stock_status', function () {
    $product = Product::factory()->product()->create([
        'name' => 'شامبو قطط',
        'quantity' => 2.0,
        'low_stock_threshold' => 5.0,
        'stock_status' => 'low',
        'track_stock' => true,
    ]);

    $invoice = Invoice::factory()->create([
        'status' => 'confirmed',
    ]);

    $invoiceItem = InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'product_id' => $product->id,
        'quantity' => 4.0,
        'unit_price' => 50.0,
        'line_total' => 200.0,
    ]);

    $this->invoiceService->cancelInvoice($invoice, 'طلب العميل إلغاء الفاتورة');

    $product->refresh();

    // 2.0 + 4.0 = 6.0 (> 5.0 threshold => available)
    expect((float) $product->quantity)->toBe(6.0)
        ->and($product->stock_status)->toBe('available');
});

test('cancelling a confirmed invoice with vaccine items restores exact quantities back to correct vaccine_batches and recalculates product quantity', function () {
    $vaccine = Product::factory()->vaccination()->create();

    $batch1 = VaccineBatch::factory()->create([
        'product_id' => $vaccine->id,
        'expiry_date' => now()->addMonths(3)->toDateString(),
        'quantity_received' => 10,
        'quantity_remaining' => 2, // Was 5, 3 sold
    ]);

    $batch2 = VaccineBatch::factory()->create([
        'product_id' => $vaccine->id,
        'expiry_date' => now()->addMonths(6)->toDateString(),
        'quantity_received' => 10,
        'quantity_remaining' => 5, // Was 7, 2 sold
    ]);

    $this->stockService->recalculateVaccineStock($vaccine);
    expect((float) $vaccine->fresh()->quantity)->toBe(7.0);

    $invoice = Invoice::factory()->create([
        'status' => 'confirmed',
    ]);

    $invoiceItem = InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'product_id' => $vaccine->id,
        'quantity' => 5.0,
        'unit_price' => 100.0,
        'line_total' => 500.0,
    ]);

    // Link batch deductions to invoice item
    InvoiceItemVaccineBatch::create([
        'invoice_item_id' => $invoiceItem->id,
        'vaccine_batch_id' => $batch1->id,
        'quantity' => 3.0,
    ]);

    InvoiceItemVaccineBatch::create([
        'invoice_item_id' => $invoiceItem->id,
        'vaccine_batch_id' => $batch2->id,
        'quantity' => 2.0,
    ]);

    $this->invoiceService->cancelInvoice($invoice, 'إلغاء الفاتورة واستعادة التطعيمات');

    expect((float) $batch1->fresh()->quantity_remaining)->toBe(5.0)
        ->and((float) $batch2->fresh()->quantity_remaining)->toBe(7.0)
        ->and((float) $vaccine->fresh()->quantity)->toBe(12.0);
});

test('cancelling an already-cancelled invoice throws exception and does not double-restore stock', function () {
    $product = Product::factory()->product()->create([
        'quantity' => 10.0,
        'track_stock' => true,
        'low_stock_threshold' => 2.0,
        'stock_status' => 'available',
    ]);

    $invoice = Invoice::factory()->create([
        'status' => 'confirmed',
    ]);

    InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'product_id' => $product->id,
        'quantity' => 5.0,
    ]);

    // First cancellation
    $this->invoiceService->cancelInvoice($invoice, 'إلغاء أولي');

    expect((float) $product->fresh()->quantity)->toBe(15.0);

    // Second cancellation attempt
    expect(function () use ($invoice) {
        $this->invoiceService->cancelInvoice($invoice->fresh(), 'إلغاء مكرر');
    })->toThrow(\RuntimeException::class);

    // Assert stock is NOT double-restored
    expect((float) $product->fresh()->quantity)->toBe(15.0);
});

test('after cancellation cancellation_reason and cancelled_at are set correctly', function () {
    $invoice = Invoice::factory()->create([
        'status' => 'confirmed',
        'cancellation_reason' => null,
        'cancelled_at' => null,
    ]);

    $reason = 'خطأ في بيانات العميل وتم الإلغاء';
    $cancelledInvoice = $this->invoiceService->cancelInvoice($invoice, $reason);

    expect($cancelledInvoice->status)->toBe('cancelled')
        ->and($cancelledInvoice->cancellation_reason)->toBe($reason)
        ->and($cancelledInvoice->cancelled_at)->not->toBeNull()
        ->and($cancelledInvoice->isCancelled())->toBeTrue()
        ->and($cancelledInvoice->isConfirmed())->toBeFalse();
});

test('a cancelled invoice is excluded from Invoice confirmed scope and revenue queries', function () {
    $invoice = Invoice::factory()->create([
        'status' => 'confirmed',
        'total' => 500.0,
    ]);

    expect(Invoice::confirmed()->where('id', $invoice->id)->exists())->toBeTrue()
        ->and(Invoice::cancelled()->where('id', $invoice->id)->exists())->toBeFalse();

    $this->invoiceService->cancelInvoice($invoice, 'إلغاء');

    expect(Invoice::confirmed()->where('id', $invoice->id)->exists())->toBeFalse()
        ->and(Invoice::cancelled()->where('id', $invoice->id)->exists())->toBeTrue();

    // Confirm that sum of confirmed invoices does not include the cancelled invoice total
    $totalConfirmedRevenue = Invoice::confirmed()->sum('total');
    expect((float) $totalConfirmedRevenue)->toBe(0.0);
});
