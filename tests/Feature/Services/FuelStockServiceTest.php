<?php

use App\Models\FuelProduct;
use App\Models\FuelPurchase;
use App\Models\Nozzle;
use App\Services\FuelStockService;
use App\Services\MeterReadingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('closing stock is opening plus received minus sold', function () {
    $product = FuelProduct::factory()->create(['opening_stock' => 8500, 'low_stock_threshold' => 1000]);
    $nozzle = Nozzle::factory()->for($product)->create();
    $readings = app(MeterReadingService::class);

    // Yesterday: +2,000 received, −500 sold → today opens at 10,000.
    FuelPurchase::factory()->for($product)->create(['business_date' => today()->subDay(), 'quantity' => 2000]);
    $readings->record($nozzle, today()->subDay(), 0, 500);

    // Today: +10,000 received, −6,200 sold.
    FuelPurchase::factory()->for($product)->create(['business_date' => today(), 'quantity' => 10000]);
    $readings->record($nozzle, today(), 500, 6700);

    $row = app(FuelStockService::class)->summary(today())->first();

    expect($row)->toMatchArray([
        'opening' => 10000.0,
        'received' => 10000.0,
        'sold' => 6200.0,
        'closing' => 13800.0,
        'is_low' => false,
    ]);
});

test('stock is flagged low at or below the threshold', function () {
    $product = FuelProduct::factory()->create(['opening_stock' => 900, 'low_stock_threshold' => 1000]);

    expect(app(FuelStockService::class)->summary(today())->first()['is_low'])->toBeTrue();
});
