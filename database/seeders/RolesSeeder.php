<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        // تم الإضافة: مسح cache الخاص بالصلاحيات قبل/بعد إنشاء الأدوار لتجنب مشاكل Spatie cache
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'employee']);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
