<?php

namespace Database\Seeders;

use App\Models\FuelProduct;
use App\Models\Pump;
use Illuminate\Database\Seeder;

/**
 * Products, pumps and nozzles for the demo station.
 */
class FuelSetupSeeder extends Seeder
{
    public function run(): void
    {
        $products = collect([
            ['code' => 'petrol', 'name' => 'Petrol', 'selling_price' => 280, 'opening_stock' => 9000, 'low_stock_threshold' => 3000],
            ['code' => 'diesel', 'name' => 'Diesel', 'selling_price' => 290, 'opening_stock' => 7000, 'low_stock_threshold' => 2500],
            ['code' => 'hi-octane', 'name' => 'Hi-Octane', 'selling_price' => 300, 'opening_stock' => 2500, 'low_stock_threshold' => 800],
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
