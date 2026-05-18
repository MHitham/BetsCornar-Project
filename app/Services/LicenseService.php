<?php

namespace App\Services;

use App\Models\AppLicense;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * ===================================================================
 * خدمة الترخيص - License Service
 * ===================================================================
 *
 * تتعامل مع كل عمليات الترخيص:
 * - توليد بصمة الجهاز (Machine Fingerprint)
 * - توليد مفتاح الترخيص
 * - التحقق من حالة الترخيص (تجريبي / مفعل / منتهي / تلاعب)
 * - حفظ واستعادة النسخة الاحتياطية المشفرة
 * - التحقق من سلامة البيانات (Checksum)
 *
 * ===================================================================
 * مستويات الأمان:
 * ===================================================================
 * 1. تشفير الحقول الحساسة في قاعدة البيانات (Laravel Encrypted Casting)
 * 2. التحقق من سلامة البيانات عبر Checksum مع مفتاح سري منفصل
 * 3. نسخة احتياطية مشفرة في ملف مخفي على النظام
 * 4. اكتشاف التلاعب بالوقت عبر مقارنة last_run_date
 * 5. بصمة الجهاز (SHA256) لربط الترخيص بجهاز محدد
 */
class LicenseService
{
    /**
     * ===================================================================
     * توليد بصمة الجهاز - Machine Fingerprint
     * ===================================================================
     *
     * يستخدم مزيج من:
     * - php_uname():      معلومات نظام التشغيل (مستقر)
     * - gethostname():    اسم الجهاز على الشبكة (مستقر)
     * - disk_total_space: السعة الكلية للقرص بالجيجابايت (مستقر)
     *
     * ملاحظة: نستخدم disk_total_space بدلاً من disk_free_space
     * لأن المساحة الحرة تتغير باستمرار مع كل عملية كتابة،
     * بينما السعة الكلية ثابتة لنفس القرص الصلب
     *
     * @return string SHA256 hash يمثل بصمة فريدة للجهاز
     */
    public static function getMachineId(): string
    {
        // تحديد مسار القرص حسب نظام التشغيل
        $diskPath = PHP_OS_FAMILY === 'Windows' ? 'C:\\' : '/';

        // جمع بيانات الجهاز
        $fingerprint = implode('|', [
            php_uname(),                                                      // معلومات نظام التشغيل
            gethostname(),                                                    // اسم الجهاز
            (string) floor(@disk_total_space($diskPath) / (1024 * 1024 * 1024)), // السعة الكلية بالـ GB (عدد صحيح)
        ]);

        return hash('sha256', $fingerprint);
    }

    /**
     * ===================================================================
     * تحويل آمن للتاريخ إلى Carbon - Safe Carbon Conversion
     * ===================================================================
     *
     * يتعامل مع الحالات التي يكون فيها التاريخ:
     * - Carbon instance (في سياق HTTP مع encrypted:datetime cast)
     * - String (في بعض سياقات CLI أو بعد refresh)
     *
     * @param mixed $date القيمة (Carbon أو string)
     * @return Carbon
     */
    private static function toSafeCarbon($date): Carbon
    {
        if ($date instanceof Carbon) {
            return $date;
        }

        return Carbon::parse($date);
    }

    /**
     * ===================================================================
     * توليد مفتاح الترخيص - Generate License Key
     * ===================================================================
     *
     * يولد مفتاح ترخيص فريد بناءً على بصمة الجهاز + المفتاح السري
     * المفتاح الناتج: أول 20 حرف من SHA256 hash بالأحرف الكبيرة
     *
     * @param string $machineId بصمة الجهاز
     * @return string مفتاح ترخيص مكون من 20 حرف
     */
    public static function generateLicenseKey(string $machineId): string
    {
        $hash = hash('sha256', $machineId . config('license.secret_key'));

        return strtoupper(substr($hash, 0, 20));
    }

    /**
     * ===================================================================
     * توليد التحقق من سلامة البيانات - Generate Checksum
     * ===================================================================
     *
     * يولد hash للتحقق من عدم تعديل بيانات الترخيص يدوياً
     * يعتمد على: machine_id + install_date + is_activated + المفتاح السري
     *
     * أي تعديل مباشر على قاعدة البيانات سيُكتشف عبر عدم تطابق الـ checksum
     *
     * @param AppLicense $license سجل الترخيص
     * @return string SHA256 checksum
     */
    public static function generateChecksum(AppLicense $license): string
    {
        // الحصول على تاريخ التثبيت كنص بشكل آمن
        // قد يكون Carbon instance أو string حسب سياق التحميل
        $installDate = $license->install_date;
        if ($installDate instanceof \Carbon\Carbon) {
            $installDateStr = $installDate->format('Y-m-d H:i:s');
        } else {
            // إذا كان نص (مثلاً بعد refresh في بعض السياقات)
            $installDateStr = \Carbon\Carbon::parse($installDate)->format('Y-m-d H:i:s');
        }

        $data = implode('|', [
            $license->machine_id,
            $installDateStr,
            $license->is_activated ? '1' : '0',
            config('license.secret_key'),
        ]);

        return hash('sha256', $data);
    }

    /**
     * ===================================================================
     * تهيئة الترخيص عند أول تشغيل - Initialize License
     * ===================================================================
     *
     * يُنشئ سجل ترخيص جديد إذا لم يكن موجوداً للجهاز الحالي:
     * - يحفظ بصمة الجهاز
     * - يسجل تاريخ التثبيت = الآن
     * - يسجل آخر تاريخ تشغيل = الآن
     * - يحسب الـ checksum
     * - يحفظ نسخة احتياطية مشفرة
     *
     * @return AppLicense سجل الترخيص (جديد أو موجود)
     */
    public static function initializeLicense(): AppLicense
    {
        $machineId = self::getMachineId();

        // البحث عن سجل موجود لهذا الجهاز
        $license = AppLicense::where('machine_id', $machineId)->first();

        if ($license) {
            return $license;
        }

        // محاولة الاستعادة من النسخة الاحتياطية (مثلاً بعد إعادة تثبيت)
        $license = self::restoreFromBackup($machineId);

        if ($license) {
            return $license;
        }

        // إنشاء سجل جديد - أول تشغيل على هذا الجهاز
        $now = now();

        $license = new AppLicense();
        $license->machine_id   = $machineId;
        $license->install_date  = $now;
        $license->last_run_date = $now;
        $license->is_activated  = false;
        $license->checksum      = 'pending'; // مؤقت - سيُحسب بعد الحفظ
        $license->save();

        // ===================================================================
        // إعادة تحميل السجل من قاعدة البيانات قبل حساب الـ checksum
        // هذا ضروري لأن encrypted:datetime قد يعيد قيمة مختلفة قليلاً
        // بين الحفظ والقراءة (بسبب إعادة التشفير/فك التشفير)
        // refresh() يضمن أن الـ checksum يُحسب من نفس القيم التي ستُقرأ لاحقاً
        // ===================================================================
        $license->refresh();

        $license->checksum = self::generateChecksum($license);
        $license->save();

        // حفظ نسخة احتياطية مشفرة
        self::saveBackup($license);

        Log::info('LicenseService: تم إنشاء ترخيص جديد للجهاز', [
            'machine_id' => substr($machineId, 0, 12) . '...',
        ]);

        return $license;
    }

    /**
     * ===================================================================
     * التحقق من حالة الترخيص - Check License
     * ===================================================================
     *
     * يتحقق من حالة الترخيص ويعيد مصفوفة بالنتيجة:
     *
     * الحالات الممكنة:
     * - activated:     مُفعل بمفتاح صالح → السماح
     * - trial:         في فترة التجربة → السماح + عدد الأيام المتبقية
     * - expired:       انتهت الفترة التجريبية → منع + عرض بصمة الجهاز
     * - time_tampered: تم اكتشاف التلاعب بالوقت → منع فوري
     * - tampered:      تم اكتشاف تلاعب بالبيانات → منع فوري
     *
     * @return array ['status' => string, ...additional data]
     */
    public static function checkLicense(): array
    {
        $machineId = self::getMachineId();

        // البحث عن سجل الترخيص لهذا الجهاز
        $license = AppLicense::where('machine_id', $machineId)->first();

        // إذا لم يوجد سجل → تهيئة أول تشغيل
        if (!$license) {
            $license = self::initializeLicense();
        }

        // ===================================================================
        // التحقق من سلامة البيانات (Checksum Validation)
        // ===================================================================
        // إذا فشل فك التشفير أو لم يتطابق الـ checksum = تلاعب
        try {
            $expectedChecksum = self::generateChecksum($license);

            if ($license->checksum !== $expectedChecksum) {
                Log::warning('LicenseService: تم اكتشاف تلاعب بالبيانات (checksum mismatch)', [
                    'machine_id' => substr($machineId, 0, 12) . '...',
                ]);

                return [
                    'status'     => 'tampered',
                    'machine_id' => $machineId,
                    'message'    => 'تم اكتشاف تلاعب بالبيانات',
                ];
            }
        } catch (\Throwable $e) {
            // فشل فك التشفير = تلاعب بالبيانات المشفرة مباشرة في SQLite
            Log::warning('LicenseService: فشل فك تشفير بيانات الترخيص', [
                'error' => $e->getMessage(),
            ]);

            return [
                'status'     => 'tampered',
                'machine_id' => $machineId,
                'message'    => 'تم اكتشاف تلاعب بالبيانات',
            ];
        }

        // ===================================================================
        // إذا مُفعل بمفتاح صالح → السماح فوراً
        // ===================================================================
        if ($license->is_activated) {
            return ['status' => 'activated'];
        }

        // ===================================================================
        // اكتشاف التلاعب بالوقت
        // ===================================================================
        // إذا الوقت الحالي أقدم من آخر تشغيل = المستخدم رجّع الساعة
        $lastRunDate = self::toSafeCarbon($license->last_run_date);
        if (now()->lt($lastRunDate)) {
            Log::warning('LicenseService: تم اكتشاف تلاعب بالوقت', [
                'now'           => now()->toDateTimeString(),
                'last_run_date' => $lastRunDate->toDateTimeString(),
            ]);

            return [
                'status'     => 'time_tampered',
                'machine_id' => $machineId,
                'message'    => 'تم اكتشاف تلاعب بالوقت - تم قفل التطبيق',
            ];
        }

        // ===================================================================
        // حساب مدة التجربة المستهلكة والمتبقية
        // ===================================================================
        $installDate = self::toSafeCarbon($license->install_date);
        
        $diff = (int) match(config('license.trial_unit', 'day')) {
            'hour' => abs(now()->diffInHours($installDate)),
            'day'  => abs(now()->diffInDays($installDate)),
            default => abs(now()->diffInDays($installDate)),
        };
        
        $trialValue = (int) config('license.trial_value', 4);

        Log::info('License Debug', [
            'now'         => now()->toDateTimeString(),
            'install'     => $installDate->toDateTimeString(),
            'last_run'    => self::toSafeCarbon($license->last_run_date)->toDateTimeString(),
            'diff'        => $diff,
            'trial_value' => $trialValue,
            'trial_unit'  => config('license.trial_unit'),
        ]);

        if ($diff >= $trialValue) {
            // الفترة التجريبية انتهت
            Log::info('LicenseService: انتهت الفترة التجريبية', [
                'units_used'  => $diff,
                'trial_value' => $trialValue,
                'trial_unit'  => config('license.trial_unit')
            ]);

            return [
                'status'     => 'expired',
                'machine_id' => $machineId,
                'units_used' => $diff,
                'message'    => 'انتهت الفترة التجريبية',
            ];
        }

        // ===================================================================
        // الفترة التجريبية مستمرة → تحديث last_run_date والمتابعة
        // ===================================================================
        $license->last_run_date = now();
        $license->checksum = self::generateChecksum($license); // تحديث الـ checksum مع كل تحديث
        $license->save();

        // تحديث النسخة الاحتياطية
        self::saveBackup($license);

        $remainingUnits = $trialValue - $diff;

        return [
            'status'          => 'trial',
            'remaining_units' => $remainingUnits,
            'units_used'      => $diff,
            'trial_unit'      => config('license.trial_unit', 'day')
        ];
    }

    /**
     * ===================================================================
     * تفعيل الترخيص - Activate License
     * ===================================================================
     *
     * يتحقق من المفتاح المُدخل ويُفعّل الترخيص إذا كان صحيحاً
     *
     * @param string $inputKey المفتاح المُدخل من المستخدم
     * @return bool true إذا تم التفعيل بنجاح
     */
    public static function activateLicense(string $inputKey): bool
    {
        $machineId = self::getMachineId();
        $license   = AppLicense::where('machine_id', $machineId)->first();

        if (!$license) {
            Log::warning('LicenseService: محاولة تفعيل بدون سجل ترخيص');
            return false;
        }

        // توليد المفتاح المتوقع ومقارنته مع المُدخل
        $expectedKey = self::generateLicenseKey($machineId);

        // المقارنة بدون حساسية للأحرف الصغيرة/الكبيرة
        if (strtoupper(trim($inputKey)) !== $expectedKey) {
            Log::warning('LicenseService: مفتاح ترخيص غير صحيح', [
                'machine_id' => substr($machineId, 0, 12) . '...',
            ]);
            return false;
        }

        // التفعيل الناجح
        $license->is_activated = true;
        $license->license_key  = $inputKey;
        $license->checksum     = self::generateChecksum($license);
        $license->save();

        // تحديث النسخة الاحتياطية
        self::saveBackup($license);

        Log::info('LicenseService: تم تفعيل الترخيص بنجاح', [
            'machine_id' => substr($machineId, 0, 12) . '...',
        ]);

        return true;
    }

    /**
     * ===================================================================
     * حفظ نسخة احتياطية مشفرة - Save Encrypted Backup
     * ===================================================================
     *
     * يحفظ بيانات الترخيص في ملف مخفي مشفر:
     * C:\ProgramData\BetsCornar\license.dat
     *
     * التشفير يتم عبر Laravel's encrypt() (AES-256-CBC + APP_KEY)
     * الملف يعمل كمرجع احتياطي إذا تم حذف قاعدة البيانات
     *
     * @param AppLicense $license سجل الترخيص
     */
    public static function saveBackup(AppLicense $license): void
    {
        try {
            $backupPath = config('license.backup_path', 'C:\\ProgramData\\BetsCornar\\license.dat');
            $dir = dirname($backupPath);

            // إنشاء المجلد إذا لم يكن موجوداً
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }

            // تجميع البيانات وتشفيرها (استخدام toSafeCarbon لتوافق كل السياقات)
            $data = [
                'machine_id'    => $license->machine_id,
                'install_date'  => self::toSafeCarbon($license->install_date)->format('Y-m-d H:i:s'),
                'last_run_date' => self::toSafeCarbon($license->last_run_date)->format('Y-m-d H:i:s'),
                'is_activated'  => $license->is_activated,
                'license_key'   => $license->getRawOriginal('license_key'), // القيمة المشفرة الخام
                'checksum'      => $license->checksum,
            ];

            // تشفير مزدوج: البيانات محولة لـ JSON ثم مشفرة بـ Laravel encrypt
            $encrypted = encrypt(json_encode($data));

            @file_put_contents($backupPath, $encrypted);

            // جعل الملف مخفياً على Windows
            if (PHP_OS_FAMILY === 'Windows' && file_exists($backupPath)) {
                @exec("attrib +H \"{$backupPath}\"");
            }
        } catch (\Throwable $e) {
            // الفشل في حفظ النسخة الاحتياطية لا يوقف التطبيق
            Log::warning('LicenseService: فشل حفظ النسخة الاحتياطية', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * ===================================================================
     * استعادة من النسخة الاحتياطية - Restore From Backup
     * ===================================================================
     *
     * يحاول استعادة بيانات الترخيص من الملف المشفر الاحتياطي
     * يُستخدم عند إعادة تثبيت التطبيق أو حذف قاعدة البيانات
     *
     * الاستعادة تنجح فقط إذا:
     * 1. الملف موجود
     * 2. فك التشفير نجح (نفس APP_KEY)
     * 3. بصمة الجهاز تتطابق (نفس الجهاز)
     *
     * @param string $machineId بصمة الجهاز الحالي
     * @return AppLicense|null سجل مُستعاد أو null
     */
    public static function restoreFromBackup(string $machineId): ?AppLicense
    {
        try {
            $backupPath = config('license.backup_path', 'C:\\ProgramData\\BetsCornar\\license.dat');

            if (!file_exists($backupPath)) {
                return null;
            }

            $encrypted = file_get_contents($backupPath);

            if (empty($encrypted)) {
                return null;
            }

            // فك تشفير البيانات
            $data = json_decode(decrypt($encrypted), true);

            if (!$data || !isset($data['machine_id'])) {
                return null;
            }

            // التحقق من أن البيانات تخص نفس الجهاز
            if ($data['machine_id'] !== $machineId) {
                Log::warning('LicenseService: بصمة الجهاز لا تتطابق مع النسخة الاحتياطية');
                return null;
            }

            // إعادة إنشاء السجل في قاعدة البيانات
            $license = new AppLicense();
            $license->machine_id   = $data['machine_id'];
            $license->install_date  = Carbon::parse($data['install_date']);
            $license->last_run_date = Carbon::parse($data['last_run_date']);
            $license->is_activated  = (bool) ($data['is_activated'] ?? false);
            $license->license_key   = null; // سيتم حفظه أدناه بالقيمة الخام المشفرة
            $license->checksum      = $data['checksum'] ?? '';
            $license->save();

            // إذا كان هناك مفتاح ترخيص محفوظ، نعيد حفظه
            // نستخدم القيمة الخام المشفرة مباشرة لتجنب التشفير المزدوج
            if (!empty($data['license_key'])) {
                \DB::table('app_licenses')
                    ->where('id', $license->id)
                    ->update(['license_key' => $data['license_key']]);
                $license->refresh();
            }

            Log::info('LicenseService: تم استعادة الترخيص من النسخة الاحتياطية');

            return $license;
        } catch (\Throwable $e) {
            Log::warning('LicenseService: فشل استعادة النسخة الاحتياطية', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * ===================================================================
     * الحصول على بصمة الجهاز الحالية - Get Current Machine ID
     * ===================================================================
     *
     * دالة مساعدة لعرض بصمة الجهاز للمستخدم (في حالة طلب مفتاح ترخيص)
     *
     * @return string بصمة الجهاز SHA256
     */
    public static function getCurrentMachineId(): string
    {
        return self::getMachineId();
    }
}
