<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * ===================================================================
 * نموذج ترخيص التطبيق - App License Model
 * ===================================================================
 *
 * يمثل سجل ترخيص مرتبط بجهاز محدد عبر بصمة الجهاز (machine_id)
 *
 * الحقول الحساسة مشفرة تلقائياً عبر Laravel Encrypted Casting:
 * - install_date:  تاريخ التثبيت الأول (لمنع التلاعب بمدة التجربة)
 * - last_run_date: آخر تاريخ تشغيل (لاكتشاف التلاعب بالوقت)
 * - license_key:   مفتاح الترخيص (لإخفائه من فحص قاعدة البيانات المباشر)
 *
 * ملاحظة: machine_id هو SHA256 hash بالفعل ولا يحتاج تشفير إضافي
 */
class AppLicense extends Model
{
    /**
     * اسم الجدول في قاعدة البيانات
     */
    protected $table = 'app_licenses';

    /**
     * الحقول القابلة للتعبئة الجماعية
     */
    protected $fillable = [
        'machine_id',
        'install_date',
        'last_run_date',
        'license_key',
        'is_activated',
        'checksum',
    ];

    /**
     * تحويلات الحقول - الحقول الحساسة مشفرة تلقائياً
     *
     * encrypted:datetime → يشفر القيمة عند الحفظ ويفك تشفيرها كـ Carbon عند القراءة
     * encrypted         → يشفر القيمة كنص عادي
     * boolean           → تحويل عادي (لا حاجة لتشفيره لأن الـ checksum يحميه)
     */
    protected function casts(): array
    {
        return [
            'install_date'  => 'encrypted:datetime',
            'last_run_date' => 'encrypted:datetime',
            'license_key'   => 'encrypted',
            'is_activated'  => 'boolean',
        ];
    }
}
