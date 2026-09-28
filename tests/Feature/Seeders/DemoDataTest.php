<?php

use App\Models\DailyClosing;
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
        ->whereColumn('sales.liters', '!=', 'meter_readings.liters')
        ->count();

    expect(Sale::count())->toBe(MeterReading::count())
        ->and($mismatched)->toBe(0);
});

test('the demo nozzle is ready for the scripted reading', function () {
    $nozzle = Nozzle::query()
        ->whereHas('pump', fn ($query) => $query->where('name', 'Pump 01'))
        ->where('number', 1)
        ->first();

    expect((float) $nozzle->current_meter)->toBe(125430.50);
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
