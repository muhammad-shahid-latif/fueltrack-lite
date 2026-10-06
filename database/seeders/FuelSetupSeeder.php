<?php

namespace Database\Seeders;

use App\Enums\ProductType;
use App\Enums\ProductUnit;
use App\Models\FuelProduct;
use App\Models\Pump;
use Illuminate\Database\Seeder;

/**
 * Products (fuel and counter items), pumps and nozzles for the demo station.
 * Selling prices here are opening prices; DemoOperationsSeeder writes the
 * price history and later changes through FuelPriceService.
 */
class FuelSetupSeeder extends Seeder
{
    public function run(): void
    {
        $products = collect([
            ['code' => 'petrol', 'name' => 'Petrol', 'type' => ProductType::Fuel, 'unit' => ProductUnit::Liter, 'selling_price' => 275, 'opening_stock' => 9000, 'low_stock_threshold' => 3000],
            ['code' => 'diesel', 'name' => 'Diesel', 'type' => ProductType::Fuel, 'unit' => ProductUnit::Liter, 'selling_price' => 290, 'opening_stock' => 7000, 'low_stock_threshold' => 2500],
            ['code' => 'hi-octane', 'name' => 'Hi-Octane', 'type' => ProductType::Fuel, 'unit' => ProductUnit::Liter, 'selling_price' => 300, 'opening_stock' => 2500, 'low_stock_threshold' => 800],
            ['code' => 'engine-oil-1l', 'name' => 'Engine Oil 1L', 'type' => ProductType::Lubricant, 'unit' => ProductUnit::Bottle, 'selling_price' => 1850, 'opening_stock' => 36, 'low_stock_threshold' => 12],
            ['code' => 'engine-oil-4l', 'name' => 'Engine Oil 4L', 'type' => ProductType::Lubricant, 'unit' => ProductUnit::Can, 'selling_price' => 6900, 'opening_stock' => 18, 'low_stock_threshold' => 6],
            ['code' => 'brake-oil', 'name' => 'Brake Oil', 'type' => ProductType::Lubricant, 'unit' => ProductUnit::Bottle, 'selling_price' => 650, 'opening_stock' => 24, 'low_stock_threshold' => 8],
            ['code' => 'coolant', 'name' => 'Radiator Coolant', 'type' => ProductType::Other, 'unit' => ProductUnit::Bottle, 'selling_price' => 900, 'opening_stock' => 20, 'low_stock_threshold' => 6],
        ])->mapWithKeys(fn (array $product) => [
            $product['code'] => FuelProduct::updateOrCreate(['code' => $product['code']], $product),
        ]);

        $layout = [
            'Pump 01' => [1 => 'petrol', 2 => 'petrol'],
            'Pump 02' => [1 => 'diesel', 2 => 'diesel'],
            'Pump 03' => [1 => 'hi-octane', 2 => 'petrol'],
        ];

        foreach ($layout as $pumpName => $nozzles) {
            $pump = Pump::updateOrCreate(['name' => $pumpName]);

            foreach ($nozzles as $number => $productCode) {
                $pump->nozzles()->updateOrCreate(
                    ['number' => $number],
                    ['fuel_product_id' => $products[$productCode]->id],
                );
            }
        }
    }
}
