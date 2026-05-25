<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        $type = fake()->randomElement(['product', 'service', 'vaccination']);
        $trackStock = $type !== 'service';

        return [
            'name' => fake()->words(2, true),
            'type' => $type,
            'price' => fake()->randomFloat(2, 50, 500),
            'quantity' => $trackStock ? fake()->randomFloat(2, 10, 100) : 0,
            'track_stock' => $trackStock,
            'stock_status' => $trackStock ? 'available' : 'available',
            'low_stock_threshold' => $trackStock ? 10 : 0,
            'is_active' => true,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function vaccination(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'vaccination',
            'track_stock' => true,
            'quantity' => 0,
            'stock_status' => 'out_of_stock',
        ]);
    }

    public function service(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'service',
            'track_stock' => false,
            'quantity' => 0,
        ]);
    }
}
