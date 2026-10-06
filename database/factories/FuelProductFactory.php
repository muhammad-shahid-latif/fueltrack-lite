<?php

namespace Database\Factories;

use App\Enums\ProductType;
use App\Enums\ProductUnit;
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
            'type' => ProductType::Fuel,
            'unit' => ProductUnit::Liter,
            'selling_price' => 280,
            'opening_stock' => 10000,
            'low_stock_threshold' => 2000,
            'is_active' => true,
        ];
    }

    public function lubricant(): static
    {
        return $this->state(fn () => [
            'name' => fake()->unique()->bothify('Engine Oil ##??'),
            'code' => fake()->unique()->bothify('oil-####'),
            'type' => ProductType::Lubricant,
            'unit' => ProductUnit::Bottle,
            'selling_price' => 1850,
            'opening_stock' => 20,
            'low_stock_threshold' => 5,
        ]);
    }
}
