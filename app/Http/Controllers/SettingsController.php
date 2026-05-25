<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingsRequest;
use App\Models\Setting;

class SettingsController extends Controller
{
    public function index()
    {

        $clinicName = Setting::get('clinic_name', 'عيادة بيطرية');

        return view('settings.index', compact('clinicName'));
    }

    public function update(UpdateSettingsRequest $request)
    {

        Setting::set('clinic_name', $request->validated('clinic_name'));

        return redirect()->back()->with('success', 'تم حفظ الإعدادات بنجاح');
    }
}
