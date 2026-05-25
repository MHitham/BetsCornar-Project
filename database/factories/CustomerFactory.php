<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => '201'.fake()->numerify('#########'),
            'address' => fake()->address(),
            'animal_type' => fake()->randomElement(['قط', 'كلب', 'طائر', 'أرنب']),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
