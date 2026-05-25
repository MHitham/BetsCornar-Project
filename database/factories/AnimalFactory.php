<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class AnimalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'name' => fake()->firstName(),
            'species' => fake()->randomElement(['cat', 'dog', 'bird', 'rabbit']),
            'breed' => fake()->optional()->word(),
            'age' => fake()->optional()->randomElement(['3 years', '8 months', '2 years']),
            'gender' => fake()->optional()->randomElement(['male', 'female']),
            'weight' => fake()->optional()->randomFloat(2, 0.5, 80),
            'color' => fake()->optional()->safeColorName(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
