<?php

namespace Database\Seeders;

use App\Enums\ProductType;
use App\Enums\ProductUnit;
use App\Models\FuelProduct;
use App\Models\Pump;
use App\Models\Tank;
use Illuminate\Database\Seeder;

/**
 * Products (fuel and counter items), tanks, pumps and nozzles for the demo station.
 * Selling prices here are opening prices; DemoOperationsSeeder writes the
 * price history and later changes through FuelPriceService.
 */
class FuelSetupSeeder extends Seeder
{
    public function run(): void
    {
        $products = collect([
            ['code' => 'petrol', 'name' => 'Petrol', 'type' => ProductType::Fuel, 'unit' => ProductUnit::Liter, 'selling_price' => 275, 'cost_price' => 265.5, 'opening_stock' => 9000, 'low_stock_threshold' => 3000],
            ['code' => 'diesel', 'name' => 'Diesel', 'type' => ProductType::Fuel, 'unit' => ProductUnit::Liter, 'selling_price' => 290, 'cost_price' => 281, 'opening_stock' => 7000, 'low_stock_threshold' => 2500],
            ['code' => 'hi-octane', 'name' => 'Hi-Octane', 'type' => ProductType::Fuel, 'unit' => ProductUnit::Liter, 'selling_price' => 300, 'cost_price' => 288, 'opening_stock' => 2500, 'low_stock_threshold' => 800],
            ['code' => 'engine-oil-1l', 'name' => 'Engine Oil 1L', 'type' => ProductType::Lubricant, 'unit' => ProductUnit::Bottle, 'selling_price' => 1850, 'cost_price' => 1600, 'opening_stock' => 36, 'low_stock_threshold' => 12],
            ['code' => 'engine-oil-4l', 'name' => 'Engine Oil 4L', 'type' => ProductType::Lubricant, 'unit' => ProductUnit::Can, 'selling_price' => 6900, 'cost_price' => 6000, 'opening_stock' => 18, 'low_stock_threshold' => 6],
            ['code' => 'brake-oil', 'name' => 'Brake Oil', 'type' => ProductType::Lubricant, 'unit' => ProductUnit::Bottle, 'selling_price' => 650, 'cost_price' => 520, 'opening_stock' => 24, 'low_stock_threshold' => 8],
            ['code' => 'coolant', 'name' => 'Radiator Coolant', 'type' => ProductType::Other, 'unit' => ProductUnit::Bottle, 'selling_price' => 900, 'cost_price' => 720, 'opening_stock' => 20, 'low_stock_threshold' => 6],
        ])->mapWithKeys(fn (array $product) => [
            $product['code'] => FuelProduct::updateOrCreate(['code' => $product['code']], $product),
        ]);

        // Underground tanks. Opening levels add up to each fuel's opening stock.
        $tanks = collect([
            ['name' => 'Tank 1 · Petrol', 'code' => 'petrol', 'capacity' => 25000, 'opening_stock' => 6000],
            ['name' => 'Tank 2 · Petrol', 'code' => 'petrol', 'capacity' => 15000, 'opening_stock' => 3000],
            ['name' => 'Tank 3 · Diesel', 'code' => 'diesel', 'capacity' => 25000, 'opening_stock' => 7000],
            ['name' => 'Tank 4 · Hi-Octane', 'code' => 'hi-octane', 'capacity' => 10000, 'opening_stock' => 2500],
        ])->mapWithKeys(fn (array $tank) => [
            $tank['name'] => Tank::updateOrCreate(['name' => $tank['name']], [
                'fuel_product_id' => $products[$tank['code']]->id,
                'capacity' => $tank['capacity'],
                'opening_stock' => $tank['opening_stock'],
                'is_active' => true,
            ]),
        ]);

        $layout = [
            'Pump 01' => [1 => ['petrol', 'Tank 1 · Petrol'], 2 => ['petrol', 'Tank 1 · Petrol']],
            'Pump 02' => [1 => ['diesel', 'Tank 3 · Diesel'], 2 => ['diesel', 'Tank 3 · Diesel']],
            'Pump 03' => [1 => ['hi-octane', 'Tank 4 · Hi-Octane'], 2 => ['petrol', 'Tank 2 · Petrol']],
        ];

        foreach ($layout as $pumpName => $nozzles) {
            $pump = Pump::updateOrCreate(['name' => $pumpName]);

            foreach ($nozzles as $number => [$productCode, $tankName]) {
                $pump->nozzles()->updateOrCreate(
                    ['number' => $number],
                    ['fuel_product_id' => $products[$productCode]->id, 'tank_id' => $tanks[$tankName]->id],
                );
            }
        }
    }
}
