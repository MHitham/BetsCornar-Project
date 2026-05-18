<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BackupDatabase extends Command
{
    /**
     * اسم ووصف الأمر في Artisan.
     */
    protected $signature = 'backup:database';

    protected $description = 'إنشاء نسخة احتياطية من قاعدة البيانات';

    public function __construct(private readonly BackupService $backupService)
    {
        parent::__construct();
    }

    /**
     * تنفيذ الأمر: إنشاء نسخة احتياطية وتسجيل النتيجة.
     */
    public function handle(): int
    {
        try {
            $filename = $this->backupService->create();

            Log::info('تم إنشاء نسخة احتياطية بنجاح', ['filename' => $filename]);
            $this->info('✓ تم إنشاء النسخة الاحتياطية: ' . $filename);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('فشل إنشاء النسخة الاحتياطية', [
                'error' => $e->getMessage(),
            ]);
            $this->error('✗ فشل إنشاء النسخة الاحتياطية: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
