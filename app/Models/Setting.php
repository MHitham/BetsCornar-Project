<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {

        try {
            $allSettings = Cache::rememberForever('settings_all', function () {
                return static::query()->pluck('value', 'key')->toArray();
            });

            return $allSettings[$key] ?? $default;
        } catch (\Exception $e) {

            return $default;
        }
    }

    public static function set(string $key, string $value): void
    {

        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );

        Cache::forget('settings_all');
    }
}
