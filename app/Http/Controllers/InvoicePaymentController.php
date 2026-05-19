<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoicePaymentController extends Controller
{
    // تسجيل دفعة على فاتورة عميل (legacy — الزر في customers/show)
    public function pay(Request $request, Invoice $invoice): RedirectResponse
    {
        $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
        ], [
            'amount.required' => 'المبلغ مطلوب',
            'amount.min' => 'المبلغ يجب أن يكون أكبر من صفر',
        ]);

        $amount = (float) $request->input('amount');
        $remaining = $invoice->remaining_amount;

        if ($amount > $remaining) {
            return back()->with('error',
                'المبلغ المدخل ('.number_format($amount, 2).' ج) أكبر من المتبقي ('.number_format($remaining, 2).' ج)'
            );
        }

        // تحديث المبلغ المدفوع على الفاتورة
        $invoice->update([
            'amount_paid' => (float) $invoice->amount_paid + $amount,
        ]);

        return back()->with('success', 'تم تسجيل الدفعة بنجاح — المتبقي: '.number_format($invoice->fresh()->remaining_amount, 2).' ج');
    }

    // تسجيل دفعة مع حفظ السجل التاريخي في invoice_payments
    public function store(Request $request, Invoice $invoice): RedirectResponse
    {
        $remaining = round((float) $invoice->total - (float) $invoice->payments()->sum('amount'), 2);

        $validated = $request->validate([
            'amount'  => ['required', 'numeric', 'min:0.01', 'max:'.$remaining],
            'notes'   => ['nullable', 'string', 'max:255'],
            'paid_at' => ['nullable', 'date'],
        ]);

        DB::transaction(function () use ($validated, $invoice) {
            InvoicePayment::create([
                'invoice_id' => $invoice->id,
                'amount'     => round((float) $validated['amount'], 2),
                'notes'      => $validated['notes'] ?? null,
                'paid_at'    => $validated['paid_at'] ?? now(),
            ]);

            $invoice->amount_paid = round((float) $invoice->payments()->sum('amount'), 2);
            $invoice->save();
        });

        return back()->with('success', 'تم تسجيل الدفعة بنجاح ✅');
    }
}

