<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInvoicePaymentRequest;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Http\RedirectResponse;

class InvoicePaymentController extends Controller
{
    // استخدام خدمة الدفعات الموحدة لضمان القفل والدفتر المالي داخل معاملة واحدة.
    public function store(StoreInvoicePaymentRequest $request, Invoice $invoice): RedirectResponse
    {
        try {
            app(InvoiceService::class)->addPayment(
                $invoice,
                (float) $request->validated('amount'),
                $request->validated('notes'),
                $request->validated('paid_at')
                    ? \Carbon\Carbon::parse($request->validated('paid_at'), 'Africa/Cairo')->utc()
                    : null,
            );
        } catch (\RuntimeException $e) {
            return back()->withErrors(['amount' => $e->getMessage()], 'payment')
                ->withInput($request->only('amount', 'notes', 'paid_at', 'payment_invoice_id'));
        }

        return back()->with('success', 'تم تسجيل الدفعة بنجاح ✅');
    }
}
