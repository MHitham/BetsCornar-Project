<?php

namespace App\Services;

use Carbon\Carbon;
use RuntimeException;

class BackupService
{
    public const FILENAME_PATTERN = 'backup_[0-9]{4}-[0-9]{2}-[0-9]{2}_[0-9]{2}-[0-9]{2}(?:-[0-9]{2}_[a-f0-9]{16})?\.sql';

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

        $this->backupPath = rtrim(config('backup.backup_path'), '/\\').DIRECTORY_SEPARATOR;
        $this->mysqldumpPath = config('backup.mysqldump_path');
        $this->mysqlPath = config('backup.mysql_path');
        $this->maxBackups = config('backup.max_backups');

        $mysql = config('database.connections.mysql');
        $this->dbHost = $mysql['host'];
        $this->dbPort = (int) $mysql['port'];
        $this->dbDatabase = $mysql['database'];
        $this->dbUsername = $mysql['username'];
        $this->dbPassword = $mysql['password'] ?? '';
    }

    public function create(): string
    {
        return $this->withBackupLock(function () {
            $filename = $this->writeBackup();
            $this->cleanup([$filename]);

            return $filename;
        });
    }

    public function list(): array
    {

        if (! is_dir($this->backupPath)) {
            return [];
        }

        $backups = [];
        foreach (glob($this->backupPath.'*.sql') ?: [] as $file) {
            if (! self::validFilename(basename($file)) || ! is_file($file) || is_link($file)) {
                continue;
            }

            $modified = @filemtime($file);
            $size = @filesize($file);
            if ($modified === false || $size === false) {
                continue;
            }

            $backups[] = [
                'filename' => basename($file),
                'created_at' => Carbon::createFromTimestamp($modified),
                'size_mb' => round($size / 1024 / 1024, 2),
            ];
        }

        usort($backups, fn ($a, $b) => $b['created_at']->timestamp <=> $a['created_at']->timestamp);

        return $backups;
    }

    public function delete(string $filename): void
    {
        $path = $this->backupFile($filename);

        $this->withBackupLock(function () use ($path) {
            if (is_file($path) && ! @unlink($path)) {
                throw new RuntimeException('تعذّر حذف ملف النسخة الاحتياطية.');
            }
        });
    }

    public function restore(string $filename): void
    {
        $path = $this->backupFile($filename);

        $this->withBackupLock(function () use ($filename, $path) {
            $source = is_file($path) && ! is_link($path) ? @fopen($path, 'rb') : false;
            if ($source === false) {
                throw new RuntimeException('النسخة المختارة غير موجودة أو تعذّر قراءتها.');
            }

            try {
                if (! fstat($source)['size']) {
                    throw new RuntimeException('ملف النسخة المختارة فارغ.');
                }

                // اكتب النسخة الوقائية من غير تنظيف؛ النسخة المختارة قد تكون أقدم ملف.
                $safetyBackup = $this->writeBackup();
                try {
                    $this->runMysql($this->mysqlPath, [], [0 => $source]);
                } catch (RuntimeException $e) {
                    throw new RuntimeException('تعذّر إكمال الاستعادة. النسخة الوقائية محفوظة باسم '.$safetyBackup, previous: $e);
                }
            } finally {
                fclose($source);
            }

            $this->cleanup([$filename, $safetyBackup]);
        });
    }

    private static function validFilename(string $filename): bool
    {
        return preg_match('/\A'.self::FILENAME_PATTERN.'\z/', $filename) === 1;
    }

    private function backupFile(string $filename): string
    {
        if (! self::validFilename($filename)) {
            throw new RuntimeException('اسم ملف النسخة الاحتياطية غير صحيح.');
        }

        return $this->backupPath.$filename;
    }

    private function withBackupLock(callable $operation): mixed
    {
        if (! is_dir($this->backupPath) && ! @mkdir($this->backupPath, 0755, true) && ! is_dir($this->backupPath)) {
            throw new RuntimeException('تعذّر إنشاء مجلد النسخ الاحتياطية.');
        }

        $lock = @fopen($this->backupPath.'.backup.lock', 'c');
        if ($lock === false) {
            throw new RuntimeException('تعذّر فتح مجلد النسخ الاحتياطية للكتابة.');
        }

        try {
            if (! flock($lock, LOCK_EX | LOCK_NB)) {
                throw new RuntimeException('فيه عملية نسخ أو استعادة جارية. حاول مرة تانية بعد انتهائها.');
            }

            return $operation();
        } finally {
            fclose($lock);
        }
    }

    private function writeBackup(): string
    {
        do {
            $filename = 'backup_'.now()->format('Y-m-d_H-i-s').'_'.bin2hex(random_bytes(8)).'.sql';
            $path = $this->backupFile($filename);
        } while (file_exists($path));

        $temporary = $path.'.tmp';
        $output = @fopen($temporary, 'xb');
        if ($output === false) {
            throw new RuntimeException('تعذّر فتح ملف النسخة الاحتياطية للكتابة.');
        }

        try {
            $this->runMysql($this->mysqldumpPath, ['--single-transaction'], [1 => $output]);
            if (! fstat($output)['size']) {
                throw new RuntimeException('ملف النسخة الناتج فارغ.');
            }
            fclose($output);

            if (! @rename($temporary, $path)) {
                throw new RuntimeException('تعذّر حفظ النسخة الاحتياطية.');
            }

            return $filename;
        } finally {
            if (is_resource($output)) {
                fclose($output);
            }
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    protected function runMysql(string $binary, array $options, array $streams): void
    {
        $command = [$binary, '--user='.$this->dbUsername, '--host='.$this->dbHost, '--port='.$this->dbPort];
        if ($this->dbPassword !== '') {
            $command[] = '--password='.$this->dbPassword;
        }

        $nullDevice = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
        $process = @proc_open([...$command, ...$options, $this->dbDatabase], $streams + [
            0 => ['file', $nullDevice, 'r'],
            1 => ['file', $nullDevice, 'w'],
            2 => ['file', $nullDevice, 'w'],
        ], $pipes, options: ['bypass_shell' => true]);

        if (! is_resource($process) || proc_close($process) !== 0) {
            throw new RuntimeException('تعذّر تشغيل أمر قاعدة البيانات. راجع إعدادات MySQL والاتصال.');
        }
    }

    private function cleanup(array $keep): void
    {
        $slots = max(0, $this->maxBackups - count($keep));
        foreach ($this->list() as $backup) {
            if (in_array($backup['filename'], $keep, true)) {
                continue;
            }
            if ($slots > 0) {
                $slots--;

                continue;
            }
            @unlink($this->backupPath.$backup['filename']);
        }
    }
}
