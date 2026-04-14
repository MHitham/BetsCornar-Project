<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// طلب إلغاء فاتورة
class CancelInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cancellation_reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [];
    }

    public function attributes(): array
    {
        return [
            'cancellation_reason' => 'سبب الإلغاء',
        ];
    }
}