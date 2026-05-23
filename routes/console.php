<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// جدولة النسخ الاحتياطية يومياً الساعة 2 صباحاً
Schedule::command('backup:database')->dailyAt('02:00');


// فحص الترخيص تلقائياً في الخلفية كل ساعة
Schedule::command('license:check')->hourly();
