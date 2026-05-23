<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $employeeRole = Role::firstOrCreate(['name' => 'employee']);

        $admin = User::updateOrCreate(
            ['email' => 'admin@bets.com'],
            [
                'name' => 'الدكتور',
                'password' => 'admin123',
            ]
        );
        $admin->syncRoles([$adminRole]);

        $employee = User::updateOrCreate(
            ['email' => 'employee@betscornar.com'],
            [
                'name' => 'الموظف',
                'password' => 'employee123',
            ]
        );
        $employee->syncRoles([$employeeRole]);
    }
}
