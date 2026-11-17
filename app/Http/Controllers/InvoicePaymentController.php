<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Http\Requests\StoreInvoicePaymentRequest;
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
                $request->validated('paid_at'),
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'تم تسجيل الدفعة بنجاح ✅');
    }
}
