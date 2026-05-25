<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppLicense extends Model
{
    protected $table = 'app_licenses';

    protected $fillable = [
        'machine_id',
        'install_date',
        'last_run_date',
        'license_key',
        'is_activated',
        'checksum',
    ];

    protected function casts(): array
    {
        return [
            'install_date' => 'encrypted:datetime',
            'last_run_date' => 'encrypted:datetime',
            'license_key' => 'encrypted',
            'is_activated' => 'boolean',
        ];
    }
}
