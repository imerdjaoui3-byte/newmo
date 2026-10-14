<?php

namespace Database\Factories;

use App\Models\Bill;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bill>
 */
class BillFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $loaded = fake()->numberBetween(10, 50);
        $sold = fake()->numberBetween(0, $loaded);

        return [
            'driver_id' => User::factory()->state(['rank' => 3]),
            'bill_date' => today(),
            'loaded_quantity' => $loaded,
            'sold_quantity' => $sold,
            'returned_quantity' => $loaded - $sold,
            'total_amount' => fake()->randomFloat(2, 0, 1000),
        ];
    }
}
