<?php

namespace App\Providers;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Native\Laravel\Contracts\ProvidesPhpIni;
use Native\Laravel\Facades\Schedule as NativeSchedule;
use Native\Laravel\Facades\Window;

class NativeAppServiceProvider implements ProvidesPhpIni
{
    public function boot(): void
    {

        $this->initializeDatabase();

        Window::open()

            ->title(config('app.name', 'عيادة بيطرية'))
            ->width(1280)
            ->height(800)
            ->minWidth(1024)
            ->minHeight(700);

        NativeSchedule::run();
    }

    protected function initializeDatabase(): void
    {

        $version = config('nativephp.version', '1.0.0');
        $flagFile = storage_path("app/.native_db_initialized_v{$version}");

        if (file_exists($flagFile)) {
            return;
        }

        try {

            Artisan::call('migrate', [
                '--force' => true,
            ]);

            if (\App\Models\User::count() === 0) {

                Artisan::call('db:seed', [
                    '--class' => 'Database\\Seeders\\RolesSeeder',
                    '--force' => true,
                ]);
                Artisan::call('db:seed', [
                    '--class' => 'Database\\Seeders\\UsersSeeder',
                    '--force' => true,
                ]);
            }

            @file_put_contents($flagFile, now()->toDateTimeString());

            Log::info("NativePHP: Database initialized successfully (v{$version})");
        } catch (\Throwable $e) {

            Log::error('NativePHP: Database initialization failed: '.$e->getMessage());
        }
    }

    public function phpIni(): array
    {
        return [
        ];
    }
}
