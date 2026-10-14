<?php

namespace Database\Factories;

use App\Models\DriverLoad;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DriverLoad>
 */
class DriverLoadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'driver_id' => User::factory()->state(['rank' => 3]),
            'product_id' => Product::factory(),
            'load_date' => today(),
            'quantity' => fake()->numberBetween(1, 20),
        ];
    }
}
