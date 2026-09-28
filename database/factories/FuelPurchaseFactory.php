<?php

namespace Database\Factories;

use App\Models\FuelProduct;
use App\Models\FuelPurchase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FuelPurchase>
 */
class FuelPurchaseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fuel_product_id' => FuelProduct::factory(),
            'business_date' => today(),
            'quantity' => 5000,
            'rate' => 270,
            'amount' => 1_350_000,
            'supplier_name' => fake()->company(),
        ];
    }
}
