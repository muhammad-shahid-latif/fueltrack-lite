<?php

namespace Database\Factories;

use App\Models\FuelProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FuelProduct>
 */
class FuelProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement(['Petrol', 'Diesel', 'Hi-Octane', 'Kerosene', 'CNG']);

        return [
            'name' => $name,
            'code' => str($name)->slug()->toString(),
            'selling_price' => 280,
            'opening_stock' => 10000,
            'low_stock_threshold' => 2000,
            'is_active' => true,
        ];
    }
}
