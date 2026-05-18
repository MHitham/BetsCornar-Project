<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingsRequest;
use App\Models\Setting;

// وحدة التحكم بإعدادات النظام — متاحة للأدمن فقط
class SettingsController extends Controller
{
    /**
     * عرض صفحة إعدادات النظام.
     */
    public function index()
    {
        // جلب اسم العيادة الحالي من الإعدادات
        $clinicName = Setting::get('clinic_name', 'عيادة بيطرية');

        return view('settings.index', compact('clinicName'));
    }

    /**
     * حفظ إعدادات النظام بعد التحقق من صحة البيانات.
     */
    public function update(UpdateSettingsRequest $request)
    {
        // حفظ اسم العيادة في الإعدادات مع مسح الكاش
        Setting::set('clinic_name', $request->validated('clinic_name'));

        return redirect()->back()->with('success', 'تم حفظ الإعدادات بنجاح');
    }
}
