<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'purchased_at' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            // إضافة is_from_clinic_cash لـ rules حتى لا يحذفها validated() — AUD-06
            'is_from_clinic_cash' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => Rule::forEach(fn ($value) => $value === 'new'
                ? ['required', Rule::in(['new'])]
                : ['bail', 'required', 'integer', 'exists:products,id']),
            'items.*.new_product_name' => ['exclude_unless:items.*.product_id,new', 'required', 'string', 'max:255'],
            'items.*.new_product_type' => ['exclude_unless:items.*.product_id,new', 'required', Rule::in(['product', 'vaccination'])],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.purchase_price_per_unit' => ['required', 'numeric', 'min:0'],
            'items.*.selling_price_per_unit' => ['required', 'numeric', 'min:0'],
            'items.*.expiry_date' => ['required_if:items.*.new_product_type,vaccination', 'nullable', 'date', 'after:today'],
            'update_selling_price' => ['nullable', 'array'],
            'update_selling_price.*' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'purchased_at.required' => 'تاريخ الشراء مطلوب',
            'items.required' => 'يجب إضافة منتج واحد على الأقل',
            'items.*.product_id.required' => 'اختر المنتج',
            'items.*.product_id.integer' => 'اختر منتجًا صالحًا من القائمة',
            'items.*.product_id.exists' => 'المنتج غير موجود',
            'items.*.new_product_name.required' => 'اكتب اسم الصنف الجديد',
            'items.*.new_product_name.string' => 'اسم الصنف الجديد يجب أن يكون نصًا',
            'items.*.new_product_name.max' => 'اسم الصنف الجديد لا يمكن أن يتجاوز 255 حرفًا',
            'items.*.new_product_type.required' => 'اختر نوع الصنف الجديد',
            'items.*.new_product_type.in' => 'اختر منتج عادي أو تطعيم',
            'items.*.quantity.required' => 'الكمية مطلوبة',
            'items.*.quantity.min' => 'الكمية يجب أن تكون أكبر من صفر',
            'items.*.purchase_price_per_unit.required' => 'سعر الشراء مطلوب',
            'items.*.selling_price_per_unit.required' => 'سعر البيع مطلوب',
            'items.*.expiry_date.required_if' => 'تاريخ الانتهاء مطلوب للتطعيم الجديد',
            'items.*.expiry_date.after' => 'تاريخ الانتهاء يجب أن يكون في المستقبل',
        ];
    }
}
