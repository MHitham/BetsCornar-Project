<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// طلب التحقق من صحة بيانات الإعدادات
class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'clinic_name' => ['required', 'string', 'max:100'],
        ];
    }
}
