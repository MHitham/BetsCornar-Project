<?php

namespace App\Console\Commands;

use App\Services\LicenseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckLicenseCommand extends Command
{
    // اسم ووصف الأمر
    protected $signature   = 'license:check';
    protected $description = 'التحقق من حالة الترخيص في الخلفية وإعادة تشغيل التطبيق إذا انتهت الفترة التجريبية';

    public function handle(): int
    {
        try {
            // التحقق من وجود جدول الترخيص أولاً
            if (! \Schema::hasTable('app_licenses')) {
                return self::SUCCESS;
            }

            // فحص حالة الترخيص
            $result = LicenseService::checkLicense();

            Log::info('license:check - نتيجة الفحص', ['status' => $result['status']]);

            // إذا كان الترخيص مفعلاً أو في فترة التجربة → لا تفعل شيئاً
            if (in_array($result['status'], ['activated', 'trial'])) {
                return self::SUCCESS;
            }

            // إذا انتهت الفترة التجريبية أو تم اكتشاف تلاعب → إعادة تشغيل التطبيق
            // عند إعادة التشغيل سيمسك الـ CheckLicense middleware الموضوع
            // ويعرض شاشة التفعيل تلقائياً
            Log::warning('license:check - الترخيص منتهي أو تم التلاعب، جاري إعادة تشغيل التطبيق', [
                'status' => $result['status'],
            ]);

            \Native\Laravel\Facades\App::restart();

        } catch (\Throwable $e) {
            Log::error('license:check - خطأ غير متوقع', ['error' => $e->getMessage()]);
        }

        return self::SUCCESS;
    }
}
