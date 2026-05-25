<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Setting::updateOrCreate(['key' => 'clinic_name'], ['value' => 'عيادة بيطرية']);

        $this->call([
            RolesSeeder::class,
            UsersSeeder::class,
        ]);
    }
}
