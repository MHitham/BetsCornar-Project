<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class VaccinationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id' => \App\Models\Customer::factory(),
            'product_id' => \App\Models\Product::factory()->vaccination(),
            'invoice_id' => \App\Models\Invoice::factory(),
            'vaccination_date' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'next_dose_date' => fake()->optional(0.8)->dateTimeBetween('now', '+1 year')?->format('Y-m-d'),
        ];
    }
}
