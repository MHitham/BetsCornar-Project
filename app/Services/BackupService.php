<?php

namespace App\Services;

use Carbon\Carbon;
use RuntimeException;

/**
 * خدمة النسخ الاحتياطي لقاعدة بيانات MySQL عبر mysqldump / mysql CLI.
 */
class BackupService
{
    private string $backupPath;

    private string $mysqldumpPath;

    private string $mysqlPath;

    private int $maxBackups;

    private string $dbHost;

    private int $dbPort;

    private string $dbDatabase;

    private string $dbUsername;

    private string $dbPassword;

    public function __construct()
    {
        // قراءة إعدادات النسخ الاحتياطي من ملف config/backup.php
        $this->backupPath = config('backup.backup_path');
        $this->mysqldumpPath = config('backup.mysqldump_path');
        $this->mysqlPath = config('backup.mysql_path');
        $this->maxBackups = config('backup.max_backups');

        // قراءة بيانات اتصال MySQL من إعدادات قاعدة البيانات
        $mysql = config('database.connections.mysql');
        $this->dbHost = $mysql['host'];
        $this->dbPort = (int) $mysql['port'];
        $this->dbDatabase = $mysql['database'];
        $this->dbUsername = $mysql['username'];
        $this->dbPassword = $mysql['password'] ?? '';
    }

    /**
     * إنشاء نسخة احتياطية جديدة عبر mysqldump وحفظها كملف .sql
     */
    public function create(): string
    {
        // إنشاء مجلد النسخ إن لم يكن موجوداً
        if (! is_dir($this->backupPath)) {
            mkdir($this->backupPath, 0755, true);
        }

        // توليد اسم الملف بالتاريخ والوقت الحالي
        $filename = 'backup_'.now()->format('Y-m-d_H-i').'.sql';

        // بناء أمر mysqldump مع معالجة حالة كلمة المرور الفارغة
        $command = sprintf(
            '"%s" --user=%s --host=%s --port=%d --single-transaction %s',
            $this->mysqldumpPath,
            escapeshellarg($this->dbUsername),
            escapeshellarg($this->dbHost),
            $this->dbPort,
            escapeshellarg($this->dbDatabase)
        );

        // إضافة كلمة المرور فقط إذا كانت غير فارغة
        if ($this->dbPassword !== '') {
            $command = sprintf(
                '"%s" --user=%s --host=%s --port=%d --password="%s" --single-transaction %s',
                $this->mysqldumpPath,
                escapeshellarg($this->dbUsername),
                escapeshellarg($this->dbHost),
                $this->dbPort,
                $this->dbPassword,
                escapeshellarg($this->dbDatabase)
            );
        }

        // تنفيذ الأمر والتقاط المخرجات
        exec($command, $output, $returnCode);

        // التحقق من نجاح التنفيذ
        if ($returnCode !== 0) {
            throw new RuntimeException('mysqldump فشل: '.implode("\n", $output));
        }

        // حفظ ملف النسخة الاحتياطية
        file_put_contents($this->backupPath.$filename, implode("\n", $output));

        // حذف النسخ القديمة إن تجاوزت الحد الأقصى
        $this->cleanup();

        return $filename;
    }

    /**
     * عرض قائمة النسخ الاحتياطية المحفوظة مرتبة من الأحدث للأقدم.
     *
     * @return array<int, array{filename: string, created_at: Carbon, size_mb: float}>
     */
    public function list(): array
    {
        // إرجاع مصفوفة فارغة إذا المجلد غير موجود
        if (! is_dir($this->backupPath)) {
            return [];
        }

        // جلب جميع ملفات .sql من مجلد النسخ
        $files = glob($this->backupPath.'*.sql');

        if ($files === false || count($files) === 0) {
            return [];
        }

        // بناء مصفوفة بيانات كل نسخة
        $backups = array_map(function (string $file) {
            return [
                'filename' => basename($file),
                'created_at' => Carbon::createFromTimestamp(filemtime($file)),
                'size_mb' => round(filesize($file) / 1024 / 1024, 2),
            ];
        }, $files);

        // ترتيب من الأحدث للأقدم
        usort($backups, fn ($a, $b) => $b['created_at']->timestamp <=> $a['created_at']->timestamp);

        return $backups;
    }

    /**
     * حذف نسخة احتياطية محددة بالاسم.
     */
    public function delete(string $filename): void
    {
        $path = $this->backupPath.$filename;

        // حذف الملف إن كان موجوداً
        if (file_exists($path)) {
            unlink($path);
        }
    }

    /**
     * استعادة نسخة احتياطية محددة — يتم إنشاء نسخة أمان أولاً.
     */
    public function restore(string $filename): void
    {
        // إنشاء نسخة أمان تلقائية قبل الاستعادة
        $this->create();

        $path = $this->backupPath.$filename;

        // التحقق من وجود ملف النسخة
        if (! file_exists($path)) {
            throw new RuntimeException('الملف غير موجود');
        }

        // بناء أمر mysql للاستعادة
        $command = sprintf(
            '"%s" --user=%s --host=%s --port=%d %s',
            $this->mysqlPath,
            escapeshellarg($this->dbUsername),
            escapeshellarg($this->dbHost),
            $this->dbPort,
            escapeshellarg($this->dbDatabase)
        );

        // إضافة كلمة المرور فقط إذا كانت غير فارغة
        if ($this->dbPassword !== '') {
            $command = sprintf(
                '"%s" --user=%s --host=%s --port=%d --password="%s" %s',
                $this->mysqlPath,
                escapeshellarg($this->dbUsername),
                escapeshellarg($this->dbHost),
                $this->dbPort,
                $this->dbPassword,
                escapeshellarg($this->dbDatabase)
            );
        }

        // استخدام proc_open لإرسال محتوى الملف عبر stdin
        $descriptors = [
            0 => ['pipe', 'r'], // stdin
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ];

        $process = proc_open($command, $descriptors, $pipes);

        if (! is_resource($process)) {
            throw new RuntimeException('فشل تشغيل أمر الاستعادة');
        }

        // كتابة محتوى ملف النسخة الاحتياطية إلى stdin
        fwrite($pipes[0], file_get_contents($path));
        fclose($pipes[0]);

        // قراءة أي أخطاء من stderr
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $returnCode = proc_close($process);

        // التحقق من نجاح الاستعادة
        if ($returnCode !== 0) {
            throw new RuntimeException('فشلت الاستعادة: '.$stderr);
        }
    }

    /**
     * حذف النسخ القديمة التي تتجاوز الحد الأقصى المسموح به.
     */
    private function cleanup(): void
    {
        // جلب جميع ملفات النسخ الاحتياطية
        $files = glob($this->backupPath.'*.sql');

        if ($files === false || count($files) <= $this->maxBackups) {
            return;
        }

        // ترتيب من الأحدث للأقدم حسب تاريخ التعديل
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));

        // حذف الملفات الزائدة عن الحد الأقصى
        $toDelete = array_slice($files, $this->maxBackups);

        foreach ($toDelete as $file) {
            unlink($file);
        }
    }
}
