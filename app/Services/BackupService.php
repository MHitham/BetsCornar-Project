<?php

namespace App\Services;

class BackupService
{
    public function create(): string
    {
        throw new \RuntimeException('النسخ الاحتياطي غير متاح حالياً في نسخة الويب. سيتم تفعيله قريباً.');
    }

    public function list(): array
    {
        return [];
    }

    public function delete(string $filename): void
    {
        throw new \RuntimeException('النسخ الاحتياطي غير متاح حالياً في نسخة الويب.');
    }

    public function restore(string $filename): void
    {
        throw new \RuntimeException('النسخ الاحتياطي غير متاح حالياً في نسخة الويب.');
    }
}
