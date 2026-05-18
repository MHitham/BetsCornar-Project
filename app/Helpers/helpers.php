<?php

if (! function_exists('dashboardKey')) {
    /**
     * إنشاء مفتاح كاش يومي ثابت حسب تاريخ بداية اليوم
     * ربط المفتاح بتاريخ اليوم الحالي لتفادي تضارب بيانات الأيام المختلفة
     */
    function dashboardKey(string $key): string
    {
        return 'dashboard.'.$key.'_'.now()->startOfDay()->toDateString();
    }
}

if (! function_exists('generateLicenseKey')) {
    /**
     * ===================================================================
     * توليد مفتاح ترخيص لبصمة جهاز محددة
     * ===================================================================
     *
     * يُستخدم لتوليد مفاتيح ترخيص للعملاء:
     * - المفتاح مرتبط ببصمة الجهاز → لا يعمل على جهاز آخر
     * - المفتاح مكون من أول 20 حرف من SHA256 hash
     * - يعتمد على المفتاح السري في config('license.secret_key')
     *
     * مثال الاستخدام:
     *   $key = generateLicenseKey('abc123def456...');
     *   // يعيد: "A1B2C3D4E5F6G7H8I9J0"
     *
     * @param string $machineId بصمة الجهاز (SHA256 hash)
     * @return string مفتاح ترخيص مكون من 20 حرف كبير
     */
    function generateLicenseKey(string $machineId): string
    {
        return \App\Services\LicenseService::generateLicenseKey($machineId);
    }
}
