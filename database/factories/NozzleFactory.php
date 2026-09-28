<?php

namespace Database\Factories;

use App\Models\FuelProduct;
use App\Models\Nozzle;
use App\Models\Pump;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Nozzle>
 */
class NozzleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pump_id' => Pump::factory(),
            'fuel_product_id' => FuelProduct::factory(),
            'number' => fake()->unique()->numberBetween(1, 250),
            'current_meter' => 0,
            'is_active' => true,
        ];
    }
}
