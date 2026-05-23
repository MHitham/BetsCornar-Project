<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('admin sees revenue bar on invoices index', function () {
    Role::create(['name' => 'admin']);
    Role::create(['name' => 'employee']);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('invoices.index', ['period' => 'month']))
        ->assertSuccessful()
        ->assertSee('الإيرادات', false)
        ->assertSee('revenueModal', false);
});

test('employee does not see revenue bar on invoices index', function () {
    Role::create(['name' => 'admin']);
    Role::create(['name' => 'employee']);

    $employee = User::factory()->create();
    $employee->assignRole('employee');

    $response = $this->actingAs($employee)->get(route('invoices.index'));

    $response->assertSuccessful();
    expect($response->getContent())->not->toContain('revenueModal');
});
