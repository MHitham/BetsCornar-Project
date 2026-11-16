<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Helpers\PhoneHelper;

class UpdateCustomerRequest extends FormRequest
{
    // السماح بالوصول لهذه الـ Request
    public function authorize(): bool
    {
        return true;
    }

    // تطبيع رقم التليفون قبل بدء عملية التحقق
    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge([
                'phone' => PhoneHelper::normalize($this->input('phone')),
            ]);
        }
    }

    // قواعد التحقق من صحة البيانات
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => [
                'required', 
                'string', 
                'max:20',
                Rule::unique('customers', 'phone')->ignore($this->route('customer')),
            ],
            'address' => ['nullable', 'string', 'max:500'],
            'animal_type' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    // تعيين أسماء الحقول لعرضها في رسائل الخطأ بشكل واضح
    public function attributes(): array
    {
        return [
            'name' => __('customers.fields.name'),
            'phone' => __('customers.fields.phone'),
            'animal_type' => __('customers.fields.animal_type'),
            'address' => __('customers.fields.address'),
            'notes' => __('customers.fields.notes'),
        ];
    }
}
