<?php

namespace App\Services;

use Carbon\Carbon;
use RuntimeException;

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

        $this->backupPath = config('backup.backup_path');
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

        if (! is_dir($this->backupPath)) {
            mkdir($this->backupPath, 0755, true);
        }

        $filename = 'backup_'.now()->format('Y-m-d_H-i').'.sql';

        $command = sprintf(
            '"%s" --user=%s --host=%s --port=%d --single-transaction %s',
            $this->mysqldumpPath,
            escapeshellarg($this->dbUsername),
            escapeshellarg($this->dbHost),
            $this->dbPort,
            escapeshellarg($this->dbDatabase)
        );

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

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new RuntimeException('mysqldump فشل: '.implode("\n", $output));
        }

        file_put_contents($this->backupPath.$filename, implode("\n", $output));

        $this->cleanup();

        return $filename;
    }

    public function list(): array
    {

        if (! is_dir($this->backupPath)) {
            return [];
        }

        $files = glob($this->backupPath.'*.sql');

        if ($files === false || count($files) === 0) {
            return [];
        }

        $backups = array_map(function (string $file) {
            return [
                'filename' => basename($file),
                'created_at' => Carbon::createFromTimestamp(filemtime($file)),
                'size_mb' => round(filesize($file) / 1024 / 1024, 2),
            ];
        }, $files);

        usort($backups, fn ($a, $b) => $b['created_at']->timestamp <=> $a['created_at']->timestamp);

        return $backups;
    }

    public function delete(string $filename): void
    {
        $path = $this->backupPath.$filename;

        if (file_exists($path)) {
            unlink($path);
        }
    }

    public function restore(string $filename): void
    {

        $this->create();

        $path = $this->backupPath.$filename;

        if (! file_exists($path)) {
            throw new RuntimeException('الملف غير موجود');
        }

        $command = sprintf(
            '"%s" --user=%s --host=%s --port=%d %s',
            $this->mysqlPath,
            escapeshellarg($this->dbUsername),
            escapeshellarg($this->dbHost),
            $this->dbPort,
            escapeshellarg($this->dbDatabase)
        );

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

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes);

        if (! is_resource($process)) {
            throw new RuntimeException('فشل تشغيل أمر الاستعادة');
        }

        fwrite($pipes[0], file_get_contents($path));
        fclose($pipes[0]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $returnCode = proc_close($process);

        if ($returnCode !== 0) {
            throw new RuntimeException('فشلت الاستعادة: '.$stderr);
        }
    }

    private function cleanup(): void
    {

        $files = glob($this->backupPath.'*.sql');

        if ($files === false || count($files) <= $this->maxBackups) {
            return;
        }

        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));

        $toDelete = array_slice($files, $this->maxBackups);

        foreach ($toDelete as $file) {
            unlink($file);
        }
    }
}
