<?php

namespace App\Services;

use App\Models\AppLicense;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class LicenseService
{
    public static function getMachineId(): string
    {

        $diskPath = PHP_OS_FAMILY === 'Windows' ? 'C:\\' : '/';

        $fingerprint = implode('|', [
            php_uname(),
            gethostname(),
            (string) floor(@disk_total_space($diskPath) / (1024 * 1024 * 1024)),
        ]);

        return hash('sha256', $fingerprint);
    }

    private static function toSafeCarbon($date): Carbon
    {
        if ($date instanceof Carbon) {
            return $date;
        }

        return Carbon::parse($date);
    }

    public static function generateLicenseKey(string $machineId): string
    {
        $hash = hash('sha256', $machineId.config('license.secret_key'));

        return strtoupper(substr($hash, 0, 20));
    }

    public static function generateChecksum(AppLicense $license): string
    {

        $installDate = $license->install_date;
        if ($installDate instanceof \Carbon\Carbon) {
            $installDateStr = $installDate->format('Y-m-d H:i:s');
        } else {

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

    public static function initializeLicense(): AppLicense
    {
        $machineId = self::getMachineId();

        $license = AppLicense::where('machine_id', $machineId)->first();

        if ($license) {
            return $license;
        }

        $license = self::restoreFromBackup($machineId);

        if ($license) {
            return $license;
        }

        $now = now();

        $license = new AppLicense;
        $license->machine_id = $machineId;
        $license->install_date = $now;
        $license->last_run_date = $now;
        $license->is_activated = false;
        $license->checksum = 'pending';
        $license->save();

        $license->refresh();

        $license->checksum = self::generateChecksum($license);
        $license->save();

        self::saveBackup($license);

        Log::info('LicenseService: تم إنشاء ترخيص جديد للجهاز', [
            'machine_id' => substr($machineId, 0, 12).'...',
        ]);

        return $license;
    }

    public static function checkLicense(): array
    {
        $machineId = self::getMachineId();

        $license = AppLicense::where('machine_id', $machineId)->first();

        if (! $license) {
            $license = self::initializeLicense();
        }

        try {
            $expectedChecksum = self::generateChecksum($license);

            if ($license->checksum !== $expectedChecksum) {
                Log::warning('LicenseService: تم اكتشاف تلاعب بالبيانات (checksum mismatch)', [
                    'machine_id' => substr($machineId, 0, 12).'...',
                ]);

                return [
                    'status' => 'tampered',
                    'machine_id' => $machineId,
                    'message' => 'تم اكتشاف تلاعب بالبيانات',
                ];
            }
        } catch (\Throwable $e) {

            Log::warning('LicenseService: فشل فك تشفير بيانات الترخيص', [
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'tampered',
                'machine_id' => $machineId,
                'message' => 'تم اكتشاف تلاعب بالبيانات',
            ];
        }

        if ($license->is_activated) {
            return ['status' => 'activated'];
        }

        $lastRunDate = self::toSafeCarbon($license->last_run_date);
        if (now()->lt($lastRunDate)) {
            Log::warning('LicenseService: تم اكتشاف تلاعب بالوقت', [
                'now' => now()->toDateTimeString(),
                'last_run_date' => $lastRunDate->toDateTimeString(),
            ]);

            return [
                'status' => 'time_tampered',
                'machine_id' => $machineId,
                'message' => 'تم اكتشاف تلاعب بالوقت - تم قفل التطبيق',
            ];
        }

        $installDate = self::toSafeCarbon($license->install_date);

        $diff = (int) match (config('license.trial_unit', 'day')) {
            'hour' => abs(now()->diffInHours($installDate)),
            'day' => abs(now()->diffInDays($installDate)),
            default => abs(now()->diffInDays($installDate)),
        };

        $trialValue = (int) config('license.trial_value', 4);

        Log::info('License Debug', [
            'now' => now()->toDateTimeString(),
            'install' => $installDate->toDateTimeString(),
            'last_run' => self::toSafeCarbon($license->last_run_date)->toDateTimeString(),
            'diff' => $diff,
            'trial_value' => $trialValue,
            'trial_unit' => config('license.trial_unit'),
        ]);

        if ($diff >= $trialValue) {

            Log::info('LicenseService: انتهت الفترة التجريبية', [
                'units_used' => $diff,
                'trial_value' => $trialValue,
                'trial_unit' => config('license.trial_unit'),
            ]);

            return [
                'status' => 'expired',
                'machine_id' => $machineId,
                'units_used' => $diff,
                'message' => 'انتهت الفترة التجريبية',
            ];
        }

        $license->last_run_date = now();
        $license->checksum = self::generateChecksum($license);
        $license->save();

        self::saveBackup($license);

        $remainingUnits = $trialValue - $diff;

        return [
            'status' => 'trial',
            'remaining_units' => $remainingUnits,
            'units_used' => $diff,
            'trial_unit' => config('license.trial_unit', 'day'),
        ];
    }

    public static function activateLicense(string $inputKey): bool
    {
        $machineId = self::getMachineId();
        $license = AppLicense::where('machine_id', $machineId)->first();

        if (! $license) {
            Log::warning('LicenseService: محاولة تفعيل بدون سجل ترخيص');

            return false;
        }

        $expectedKey = self::generateLicenseKey($machineId);

        if (strtoupper(trim($inputKey)) !== $expectedKey) {
            Log::warning('LicenseService: مفتاح ترخيص غير صحيح', [
                'machine_id' => substr($machineId, 0, 12).'...',
            ]);

            return false;
        }

        $license->is_activated = true;
        $license->license_key = $inputKey;
        $license->checksum = self::generateChecksum($license);
        $license->save();

        self::saveBackup($license);

        Log::info('LicenseService: تم تفعيل الترخيص بنجاح', [
            'machine_id' => substr($machineId, 0, 12).'...',
        ]);

        return true;
    }

    public static function saveBackup(AppLicense $license): void
    {
        try {
            $backupPath = config('license.backup_path', 'C:\\ProgramData\\BetsCornar\\license.dat');
            $dir = dirname($backupPath);

            if (! is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }

            $data = [
                'machine_id' => $license->machine_id,
                'install_date' => self::toSafeCarbon($license->install_date)->format('Y-m-d H:i:s'),
                'last_run_date' => self::toSafeCarbon($license->last_run_date)->format('Y-m-d H:i:s'),
                'is_activated' => $license->is_activated,
                'license_key' => $license->getRawOriginal('license_key'),
                'checksum' => $license->checksum,
            ];

            $encrypted = encrypt(json_encode($data));

            @file_put_contents($backupPath, $encrypted);

            if (PHP_OS_FAMILY === 'Windows' && file_exists($backupPath)) {
                @exec("attrib +H \"{$backupPath}\"");
            }
        } catch (\Throwable $e) {

            Log::warning('LicenseService: فشل حفظ النسخة الاحتياطية', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public static function restoreFromBackup(string $machineId): ?AppLicense
    {
        try {
            $backupPath = config('license.backup_path', 'C:\\ProgramData\\BetsCornar\\license.dat');

            if (! file_exists($backupPath)) {
                return null;
            }

            $encrypted = file_get_contents($backupPath);

            if (empty($encrypted)) {
                return null;
            }

            $data = json_decode(decrypt($encrypted), true);

            if (! $data || ! isset($data['machine_id'])) {
                return null;
            }

            if ($data['machine_id'] !== $machineId) {
                Log::warning('LicenseService: بصمة الجهاز لا تتطابق مع النسخة الاحتياطية');

                return null;
            }

            $license = new AppLicense;
            $license->machine_id = $data['machine_id'];
            $license->install_date = Carbon::parse($data['install_date']);
            $license->last_run_date = Carbon::parse($data['last_run_date']);
            $license->is_activated = (bool) ($data['is_activated'] ?? false);
            $license->license_key = null;
            $license->checksum = $data['checksum'] ?? '';
            $license->save();

            if (! empty($data['license_key'])) {
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

    public static function getCurrentMachineId(): string
    {
        return self::getMachineId();
    }
}
