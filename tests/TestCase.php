<?php

namespace Tests;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;

abstract class TestCase extends BaseTestCase
{
    protected function actingAsAdmin(): User
    {
        Role::findOrCreate('admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        return $user;
    }

    protected function actingAsEmployee(): User
    {
        Role::findOrCreate('employee', 'web');
        $user = User::factory()->create();
        $user->assignRole('employee');
        $this->actingAs($user);

        return $user;
    }

    protected function consultationProduct(float $price = 100): Product
    {
        return Product::factory()->service()->create([
            'name' => 'كشف',
            'price' => $price,
            'is_active' => true,
        ]);
    }

    // إرفاق رمز إرسال فريد بطلبات الاختبار المحمية من التكرار.
    protected function postWithToken(string $uri, array $data = []): TestResponse
    {
        return $this->post($uri, array_merge([
            '_submit_token' => (string) Str::uuid(),
        ], $data));
    }
}
