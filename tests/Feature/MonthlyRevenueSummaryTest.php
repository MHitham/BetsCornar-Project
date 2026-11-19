<?php

use App\Models\Invoice;
use App\Models\InvoiceReturn;
use App\Models\User;
use App\Services\InvoiceService;

it('filters monthly cash refund list by invoice creator', function () {
    $first = User::factory()->create();
    $second = User::factory()->create();
    $invoiceOne = Invoice::factory()->create(['created_by' => $first->id, 'status' => 'confirmed', 'created_at' => now()]);
    $invoiceTwo = Invoice::factory()->create(['created_by' => $second->id, 'status' => 'confirmed', 'created_at' => now()]);
    $returnOne = InvoiceReturn::create(['invoice_id' => $invoiceOne->id, 'reason' => 'first', 'total_refund' => 10, 'cash_refunded' => 10, 'created_at' => now()]);
    InvoiceReturn::create(['invoice_id' => $invoiceTwo->id, 'reason' => 'second', 'total_refund' => 20, 'cash_refunded' => 20, 'created_at' => now()]);

    $summary = app(InvoiceService::class)->getMonthlyRevenueSummary(now()->year, now()->month, $first->id);

    expect($summary['cash_refunds_list']->modelKeys())->toBe([$returnOne->id]);
});
