<?php

use App\Models\DailyClosing;
use App\Models\FuelProduct;
use App\Models\MeterReading;
use App\Models\Nozzle;
use App\Models\Sale;
use App\Services\DailyClosingService;
use App\Services\FuelStockService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed());

test('demo data covers ten days with nine closed', function () {
    expect(MeterReading::query()->distinct()->count('business_date'))->toBe(10)
        ->and(DailyClosing::count())->toBe(9)
        ->and(app(DailyClosingService::class)->isClosed(today()))->toBeFalse();
});

test('every reading has a matching sale', function () {
    $mismatched = Sale::query()
        ->join('meter_readings', 'meter_readings.id', '=', 'sales.meter_reading_id')
        ->whereColumn('sales.quantity', '!=', 'meter_readings.liters')
        ->count();

    expect(Sale::query()->whereNotNull('meter_reading_id')->count())->toBe(MeterReading::count())
        ->and($mismatched)->toBe(0);
});

test('the demo nozzle is ready for the scripted reading', function () {
    $nozzle = Nozzle::query()
        ->whereHas('pump', fn ($query) => $query->where('name', 'Pump 01'))
        ->where('number', 1)
        ->first();

    expect((float) $nozzle->current_meter)->toBe(125430.50);
});

test('past days have all three shifts and today has the morning shift', function () {
    $shifts = fn ($date) => MeterReading::query()->whereDate('business_date', $date)->distinct()->pluck('shift')->map->value->sort()->values()->all();

    expect($shifts(today()->subDay()))->toBe(['evening', 'morning', 'night'])
        ->and($shifts(today()))->toBe(['morning']);
});

test('price history includes the petrol price change', function () {
    $petrol = FuelProduct::firstWhere('code', 'petrol');

    expect($petrol->prices()->count())->toBe(2)
        ->and((float) $petrol->selling_price)->toBe(280.0)
        ->and(Sale::query()->where('fuel_product_id', $petrol->id)->distinct()->pluck('rate')->map(fn ($r) => (float) $r)->sort()->values()->all())->toBe([275.0, 280.0]);
});

test('counter items are sold and purchased', function () {
    expect(Sale::query()->whereNull('meter_reading_id')->count())->toBeGreaterThan(0);
});

test('stock never goes negative', function () {
    foreach (range(9, 0) as $daysAgo) {
        app(FuelStockService::class)->summary(today()->subDays($daysAgo))
            ->each(fn (array $row) => expect($row['closing'])->toBeGreaterThan(0));
    }
});

test('closings agree with the underlying sales', function () {
    DailyClosing::all()->each(function (DailyClosing $closing) {
        $sales = (float) Sale::query()->whereDate('business_date', $closing->business_date)->sum('amount');

        expect(round($sales, 2))->toBe((float) $closing->total_sales)
            ->and((float) $closing->cash_sales)->toBe(round((float) $closing->total_sales - (float) $closing->card_sales - (float) $closing->credit_sales, 2));
    });
});
