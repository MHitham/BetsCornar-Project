<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Http\Request;

class InvoiceReturnController extends Controller
{
    public function __construct(private readonly InvoiceService $invoiceService) {}

    public function store(Request $request, Invoice $invoice)
    {
        $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.invoice_item_id' => ['required', 'integer', 'exists:invoice_items,id'],
            'items.*.quantity_returned' => ['required', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $items = collect($request->items)
            ->filter(fn ($i) => (float) ($i['quantity_returned'] ?? 0) > 0)
            ->values()
            ->toArray();

        if (empty($items)) {
            return back()->withErrors(['items' => 'يجب إدخال كمية مرتجعة واحدة على الأقل.']);
        }

        try {
            $this->invoiceService->createReturn($invoice, $items, $request->reason);

            return back()->with('success', 'تم تسجيل المرتجع وإرجاع الكميات إلى المخزون بنجاح.');
        } catch (\RuntimeException $e) {
            return back()->withErrors(['return' => $e->getMessage()]);
        }
    }
}
