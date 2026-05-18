<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

// نموذج الإعدادات العامة للنظام مع تخزين مؤقت (cache)
class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * جلب قيمة إعداد معيّن من الكاش أو قاعدة البيانات.
     *
     * @param  array<string, string>  $allSettings  — شكل المصفوفة المُخزّنة
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        // حماية من الانهيار لو جدول settings غير موجود بعد أول تشغيل
        try {
            $allSettings = Cache::rememberForever('settings_all', function () {
                return static::query()->pluck('value', 'key')->toArray();
            });
            return $allSettings[$key] ?? $default;
        } catch (\Exception $e) {
            // إرجاع القيمة الافتراضية لو الجدول مش موجود بعد
            return $default;
        }
    }

    /**
     * تعيين قيمة إعداد معيّن مع مسح الكاش لإعادة تحميل البيانات.
     */
    public static function set(string $key, string $value): void
    {
        // إنشاء أو تحديث السجل في قاعدة البيانات
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );

        // مسح الكاش لإعادة تحميل الإعدادات في الطلب التالي
        Cache::forget('settings_all');
    }
}
