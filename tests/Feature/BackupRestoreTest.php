<?php

use App\Services\BackupService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->backupDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'wintools-backup-test-'.Str::uuid();
    File::makeDirectory($this->backupDirectory);
    config([
        'backup.backup_path' => $this->backupDirectory,
        'backup.max_backups' => 2,
        'backup.mysql_path' => 'mysql-test',
        'backup.mysqldump_path' => 'dump-test',
    ]);
    $this->backupService = new class extends BackupService
    {
        public string $current = 'data before restore';

        public bool $failDump = false;

        public bool $failImport = false;

        protected function runMysql(string $binary, array $options, array $streams): void
        {
            if ($binary === 'dump-test') {
                fwrite($streams[1], $this->current);
                if ($this->failDump) {
                    throw new RuntimeException('failed dump');
                }

                return;
            }

            if ($this->failImport) {
                throw new RuntimeException('failed import');
            }

            $this->current = stream_get_contents($streams[0]);
        }
    };
});

afterEach(function () {
    File::deleteDirectory($this->backupDirectory);
});

it('restores the oldest legacy file in the same minute and preserves the safety copy', function () {
    $this->freezeTime();
    $this->actingAsAdmin();
    $this->app->instance(BackupService::class, $this->backupService);
    $legacy = 'backup_'.now()->format('Y-m-d_H-i').'.sql';
    $legacyPath = $this->backupDirectory.DIRECTORY_SEPARATOR.$legacy;
    file_put_contents($legacyPath, 'original clinic data');
    touch($legacyPath, time() - 60);
    $other = $this->backupService->create();
    file_put_contents($this->backupDirectory.DIRECTORY_SEPARATOR.'unmanaged.sql', 'unrelated file');

    $this->postWithToken(route('backup.restore', $legacy))->assertRedirect(route('backup.index'))
        ->assertSessionHas('success');

    $filenames = array_column($this->backupService->list(), 'filename');
    expect($this->backupService->current)->toBe('original clinic data')
        ->and(file_get_contents($legacyPath))->toBe('original clinic data')
        ->and($filenames)->toHaveCount(2)->toContain($legacy)->not->toContain($other)
        ->and(is_file($this->backupDirectory.DIRECTORY_SEPARATOR.'unmanaged.sql'))->toBeTrue();

    $safety = array_values(array_diff($filenames, [$legacy]))[0];
    expect(file_get_contents($this->backupDirectory.DIRECTORY_SEPARATOR.$safety))->toBe('data before restore');
    $this->postWithToken(route('backup.restore', $safety))->assertSessionHas('success');
    expect($this->backupService->current)->toBe('data before restore');
    $this->delete(route('backup.destroy', $safety))->assertSessionHas('success');
});

it('does not import or leave a partial backup when the safety dump fails', function () {
    $source = $this->backupService->create();
    $this->backupService->current = 'changed data';
    $this->backupService->failDump = true;

    expect(fn () => $this->backupService->restore($source))->toThrow(RuntimeException::class, 'failed dump');
    expect($this->backupService->current)->toBe('changed data')
        ->and(array_column($this->backupService->list(), 'filename'))->toBe([$source])
        ->and(glob($this->backupDirectory.DIRECTORY_SEPARATOR.'*.tmp'))->toBe([]);
});

it('keeps the source and tells the operator which safety backup to use after a failed import', function () {
    $this->freezeTime();
    $source = $this->backupService->create();
    $this->backupService->current = 'changed data';
    $this->backupService->failImport = true;

    expect(fn () => $this->backupService->restore($source))->toThrow(RuntimeException::class, 'النسخة الوقائية محفوظة باسم');
    $filenames = array_column($this->backupService->list(), 'filename');
    expect($filenames)->toHaveCount(2)->toContain($source);
    $safety = array_values(array_diff($filenames, [$source]))[0];
    expect(file_get_contents($this->backupDirectory.DIRECTORY_SEPARATOR.$source))->toBe('data before restore')
        ->and(file_get_contents($this->backupDirectory.DIRECTORY_SEPARATOR.$safety))->toBe('changed data');
});

it('rejects a missing or empty source before writing a safety backup', function () {
    $filename = 'backup_'.now()->format('Y-m-d_H-i').'.sql';
    expect(fn () => $this->backupService->restore($filename))->toThrow(RuntimeException::class, 'النسخة المختارة غير موجودة');
    expect($this->backupService->list())->toBe([]);

    touch($this->backupDirectory.DIRECTORY_SEPARATOR.$filename);
    expect(fn () => $this->backupService->restore($filename))->toThrow(RuntimeException::class, 'ملف النسخة المختارة فارغ');
    expect(array_column($this->backupService->list(), 'filename'))->toBe([$filename]);
});
