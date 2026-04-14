<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// طلب تعديل منتج
class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['product', 'service', 'vaccination'])],
            'price' => ['required', 'numeric', 'min:0'],
            'quantity' => ['required', 'numeric', 'min:0'],
            'low_stock_threshold' => ['required', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [];
    }

    public function attributes(): array
    {
        return [
            'name' => 'الاسم',
            'price' => 'السعر',
            'quantity' => 'الكمية',
            'type' => 'النوع',
            'low_stock_threshold' => 'حد المخزون المنخفض',
            'is_active' => 'الحالة',
            'notes' => 'الملاحظات',
            'track_stock' => 'تتبع المخزون',
            'stock_status' => 'حالة المخزون',
        ];
    }
}