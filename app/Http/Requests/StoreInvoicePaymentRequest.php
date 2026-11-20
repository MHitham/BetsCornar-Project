<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoicePaymentRequest extends FormRequest
{
    protected $errorBag = 'payment';

    // السماح للمستخدمين المصرح لهم بالوصول إلى مسار تسجيل الدفعات.
    public function authorize(): bool
    {
        return true;
    }

    // ترك فحص الحد الأقصى للخدمة بعد قفل الفاتورة داخل المعاملة.
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:255'],
            'paid_at' => ['nullable', 'date'],
        ];
    }

    // تعريف أسماء الحقول بالعربية في رسائل التحقق.
    public function attributes(): array
    {
        return [
            'amount' => 'المبلغ',
            'notes' => 'ملاحظات',
            'paid_at' => 'تاريخ الدفع',
        ];
    }

    // توحيد رسائل التحقق الخاصة بنموذج الدفعة.
    public function messages(): array
    {
        return [
            'amount.required' => 'المبلغ مطلوب.',
            'amount.numeric' => 'المبلغ يجب أن يكون رقماً.',
            'amount.min' => 'المبلغ يجب أن يكون أكبر من صفر.',
            'notes.string' => 'ملاحظات يجب أن تكون نصاً.',
            'notes.max' => 'ملاحظات يجب ألا تتجاوز 255 حرفاً.',
            'paid_at.date' => 'تاريخ الدفع غير صالح.',
        ];
    }
}
