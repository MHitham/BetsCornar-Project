<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'supplier_id'                        => ['nullable', 'exists:suppliers,id'],
            'purchased_at'                       => ['required', 'date'],
            'notes'                              => ['nullable', 'string'],
            'amount_paid'                        => ['nullable', 'numeric', 'min:0'],
            'items'                              => ['required', 'array', 'min:1'],
            'items.*.product_id'                 => ['required', 'exists:products,id'],
            'items.*.quantity'                   => ['required', 'numeric', 'min:0.01'],
            'items.*.purchase_price_per_unit'    => ['required', 'numeric', 'min:0'],
            'items.*.selling_price_per_unit'     => ['required', 'numeric', 'min:0'],
            'items.*.expiry_date'                => ['nullable', 'date', 'after:today'],
            'update_selling_price'               => ['nullable', 'array'],
            'update_selling_price.*'             => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'purchased_at.required'                    => 'تاريخ الشراء مطلوب',
            'items.required'                           => 'يجب إضافة منتج واحد على الأقل',
            'items.*.product_id.required'              => 'اختر المنتج',
            'items.*.product_id.exists'                => 'المنتج غير موجود',
            'items.*.quantity.required'                => 'الكمية مطلوبة',
            'items.*.quantity.min'                     => 'الكمية يجب أن تكون أكبر من صفر',
            'items.*.purchase_price_per_unit.required' => 'سعر الشراء مطلوب',
            'items.*.selling_price_per_unit.required'  => 'سعر البيع مطلوب',
            'items.*.expiry_date.after'                => 'تاريخ الانتهاء يجب أن يكون في المستقبل',
        ];
    }
}
