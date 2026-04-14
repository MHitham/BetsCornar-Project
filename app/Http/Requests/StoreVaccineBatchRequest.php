<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// طلب إنشاء دفعة لقاح جديدة
class StoreVaccineBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('quantity_remaining')) {
            $this->merge([
                'quantity_remaining' => $this->input('quantity_received'),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where(fn ($query) => $query
                    ->where('type', 'vaccination')
                    ->where('track_stock', true)),
            ],
            'batch_code' => ['nullable', 'string', 'max:255'],
            'received_date' => ['required', 'date'],
            'expiry_date' => ['required', 'date', 'after_or_equal:received_date'],
            'quantity_received' => ['required', 'numeric', 'min:0.01'],
            'quantity_remaining' => ['required', 'numeric', 'min:0', 'lte:quantity_received'],
        ];
    }

    public function messages(): array
    {
        return [];
    }

    public function attributes(): array
    {
        return [
            'product_id' => 'المنتج',
            'batch_code' => 'كود الدفعة',
            'received_date' => 'تاريخ الاستلام',
            'expiry_date' => 'تاريخ الانتهاء',
            'quantity_received' => 'الكمية المستلمة',
            'quantity_remaining' => 'الكمية المتبقية',
        ];
    }
}