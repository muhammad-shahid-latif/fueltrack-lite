<?php

use App\Models\FuelProduct;
use App\Models\Nozzle;
use App\Services\DailyClosingService;
use App\Services\MeterReadingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(MeterReadingService::class);
    $this->nozzle = Nozzle::factory()
        ->for(FuelProduct::factory()->state(['selling_price' => 280]))
        ->create(['current_meter' => 125430.50]);
});

test('it calculates liters and sales amount from the meters', function () {
    $reading = $this->service->record($this->nozzle, today(), 125430.50, 126240.50);

    expect((float) $reading->liters)->toBe(810.0)
        ->and((float) $reading->sale->rate)->toBe(280.0)
        ->and((float) $reading->sale->amount)->toBe(226800.0);
});

test('it advances the nozzle meter to the closing reading', function () {
    $this->service->record($this->nozzle, today(), 125430.50, 126240.50);

    expect((float) $this->nozzle->fresh()->current_meter)->toBe(126240.50);
});

test('the sale keeps its rate after the price changes', function () {
    $reading = $this->service->record($this->nozzle, today(), 125430.50, 125530.50);

    $this->nozzle->fuelProduct->update(['selling_price' => 300]);

    expect((float) $reading->sale->fresh()->amount)->toBe(28000.0);
});

test('closing meter must be greater than opening meter', function () {
    $this->service->record($this->nozzle, today(), 125430.50, 125430.50);
})->throws(ValidationException::class, 'Closing meter must be greater');

test('opening meter must continue from the last closing reading', function () {
    $this->service->record($this->nozzle, today(), 125000.00, 126000.00);
})->throws(ValidationException::class, 'Opening meter must match');

test('readings cannot be added to a closed day', function () {
    app(DailyClosingService::class)->close(today(), openingCash: 0, actualCash: 0);

    $this->service->record($this->nozzle, today(), 125430.50, 126240.50);
})->throws(ValidationException::class, 'already closed');
