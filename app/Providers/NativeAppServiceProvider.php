<?php

namespace App\Providers;

use Native\Laravel\Contracts\ProvidesPhpIni;
use Native\Laravel\Facades\Window;
use Native\Laravel\Facades\Schedule as NativeSchedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class NativeAppServiceProvider implements ProvidesPhpIni
{
    public function boot(): void
    {
        // تهيئة قاعدة البيانات مرة واحدة بس (migrate + seed)
        $this->initializeDatabase();

        Window::open()
            // استخدام اسم التطبيق من الـ config بدل الداتابيز وقت الـ build
            ->title(config('app.name', 'عيادة بيطرية'))
            ->width(1280)
            ->height(800)
            ->minWidth(1024)
            ->minHeight(700);

        // تفعيل Laravel Scheduler داخل NativePHP - يشغل `schedule:run` كل دقيقة
        // هذا ضروري لتفعيل النسخ الاحتياطي التلقائي الساعة 2 صباحاً وغيره من الجداول
        NativeSchedule::run();
    }

    /**
     * تهيئة قاعدة البيانات - تشتغل مرة واحدة بس عند أول تشغيل أو بعد التحديث
     */
    protected function initializeDatabase(): void
    {
        // استخدام flag file مرتبط بالنسخة عشان يشتغل مرة واحدة بس
        // ولو النسخة اتغيرت (تحديث) هيشتغل تاني عشان يعمل migrations جديدة
        $version = config('nativephp.version', '1.0.0');
        $flagFile = storage_path("app/.native_db_initialized_v{$version}");

        if (file_exists($flagFile)) {
            return;
        }

        try {
            // تشغيل الـ migrations عشان يعمل الجداول
            Artisan::call('migrate', [
                '--force' => true,
            ]);

            // تشغيل الـ seeders مرة واحدة بس لو مفيش users
            if (\App\Models\User::count() === 0) {
                // الـ Roles الأول لأن الـ Users بتعتمد عليها
                Artisan::call('db:seed', [
                    '--class' => 'Database\\Seeders\\RolesSeeder',
                    '--force' => true,
                ]);
                Artisan::call('db:seed', [
                    '--class' => 'Database\\Seeders\\UsersSeeder',
                    '--force' => true,
                ]);
            }

            // إنشاء الـ flag file عشان ميشتغلش تاني
            @file_put_contents($flagFile, now()->toDateTimeString());

            Log::info("NativePHP: Database initialized successfully (v{$version})");
        } catch (\Throwable $e) {
            // لو حصل أي error اعمل log بس ما توقفش الـ app
            Log::error('NativePHP: Database initialization failed: ' . $e->getMessage());
        }
    }

    /**
     * Return an array of php.ini directives to be set.
     */
    public function phpIni(): array
    {
        return [
        ];
    }
}
