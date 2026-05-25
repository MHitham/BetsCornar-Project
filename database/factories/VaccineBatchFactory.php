<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class VaccineBatchFactory extends Factory
{
    public function definition(): array
    {
        $qty = fake()->randomFloat(2, 10, 50);

        return [
            'product_id' => \App\Models\Product::factory()->vaccination(),
            'batch_code' => 'BATCH-'.fake()->unique()->numerify('####'),
            'received_date' => fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'expiry_date' => fake()->dateTimeBetween('+1 month', '+1 year')->format('Y-m-d'),
            'quantity_received' => $qty,
            'quantity_remaining' => $qty,
        ];
    }
}
