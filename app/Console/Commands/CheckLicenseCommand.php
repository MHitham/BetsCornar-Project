<?php

namespace App\Console\Commands;

use App\Services\LicenseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckLicenseCommand extends Command
{
    protected $signature = 'license:check';

    protected $description = 'التحقق من حالة الترخيص في الخلفية وإعادة تشغيل التطبيق إذا انتهت الفترة التجريبية';

    public function handle(): int
    {
        try {

            if (! \Schema::hasTable('app_licenses')) {
                return self::SUCCESS;
            }

            $result = LicenseService::checkLicense();

            Log::info('license:check - نتيجة الفحص', ['status' => $result['status']]);

            if (in_array($result['status'], ['activated', 'trial'])) {
                return self::SUCCESS;
            }

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
