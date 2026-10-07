<?php

namespace Database\Factories;

use App\Models\FuelProduct;
use App\Models\Tank;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tank>
 */
class TankFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Tank '.fake()->unique()->numberBetween(1, 999),
            'fuel_product_id' => FuelProduct::factory(),
            'capacity' => 20000,
            'opening_stock' => 5000,
            'is_active' => true,
        ];
    }
}
