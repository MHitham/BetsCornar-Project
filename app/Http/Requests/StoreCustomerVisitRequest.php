<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'animal_type' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'animal_id' => [
                'nullable',
                function ($attribute, $value, $fail) {
                    if ($value !== 'new_animal' && ! \App\Models\Animal::where('id', $value)->exists()) {
                        $fail('The selected animal is invalid.');
                    }
                },
            ],
            'new_animal' => ['nullable', 'array'],
            'new_animal.name' => ['required_if:animal_id,new_animal', 'string', 'max:255'],
            'new_animal.species' => ['required_if:animal_id,new_animal', 'string', 'max:255'],
            'new_animal.breed' => ['nullable', 'string', 'max:255'],
            'new_animal.age' => ['nullable', 'string', 'max:50'],
            'new_animal.gender' => ['nullable', 'string', 'in:male,female'],
            'new_animal.weight' => ['nullable', 'numeric', 'min:0'],
            'new_animal.color' => ['nullable', 'string', 'max:255'],
            'new_animal.notes' => ['nullable', 'string', 'max:1000'],
            'diagnosis' => ['nullable', 'string'],

            'consultation_price' => ['required', 'numeric', 'min:0'],

            'amount_paid' => ['nullable', 'numeric', 'min:0'],

            'vaccinations' => ['nullable', 'array'],
            'vaccinations.*.vaccine_product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where(fn ($q) => $q->where('is_active', true)->where('type', 'vaccination')),
            ],
            'vaccinations.*.vaccine_quantity' => ['required', 'numeric', 'min:0.01'],
            'vaccinations.*.vaccine_unit_price' => ['required', 'numeric', 'min:0'],
            'vaccinations.*.vaccination_date' => ['required', 'date'],
            'vaccinations.*.next_dose_date' => ['required', 'date', 'after:vaccinations.*.vaccination_date'],

            'additional_items' => ['nullable', 'array'],
            'additional_items.*.product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where(fn ($q) => $q->where('is_active', true)),
            ],
            'additional_items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'additional_items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => __('customers.fields.name'),
            'phone' => __('customers.fields.phone'),
            'animal_type' => __('customers.fields.animal_type'),
            'consultation_price' => __('customers.visit.consultation_price'),

            'vaccinations.*.vaccine_product_id' => __('customers.visit.vaccine_product'),
            'vaccinations.*.vaccine_quantity' => __('customers.visit.vaccine_quantity'),
            'vaccinations.*.vaccine_unit_price' => __('customers.visit.unit_price'),
            'vaccinations.*.vaccination_date' => __('customers.visit.vaccination_date'),
            'vaccinations.*.next_dose_date' => __('customers.visit.next_dose_date'),
        ];
    }
}
