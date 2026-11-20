<?php

use App\Models\Animal;
use App\Models\Invoice;

it('accepts an existing animal name column and backfills only missing invoice snapshots', function () {
    $animal = Animal::factory()->create(['name' => 'روكي']);
    $missing = Invoice::factory()->create(['customer_id' => $animal->customer_id, 'animal_id' => $animal->id]);
    $saved = Invoice::factory()->create([
        'customer_id' => $animal->customer_id,
        'animal_id' => $animal->id,
        'animal_name' => 'الاسم وقت الفاتورة',
    ]);

    $migration = require database_path('migrations/2026_11_19_120000_add_animal_name_to_invoices_table.php');
    $migration->up();

    expect($missing->fresh()->animal_name)->toBe('روكي')
        ->and($saved->fresh()->animal_name)->toBe('الاسم وقت الفاتورة');
});
