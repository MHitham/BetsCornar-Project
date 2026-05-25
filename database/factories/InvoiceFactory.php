<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'invoice_number' => 'INV-'.fake()->unique()->numerify('######'),
            'customer_id' => \App\Models\Customer::factory(),
            'customer_name' => fake()->name(),
            'source' => fake()->randomElement(['customer', 'quick_sale']),
            'total' => fake()->randomFloat(2, 100, 1000),
            'status' => 'confirmed',
        ];
    }
}
