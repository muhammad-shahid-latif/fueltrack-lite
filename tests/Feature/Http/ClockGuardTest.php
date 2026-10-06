<?php

use App\Enums\Shift;
use App\Models\FuelProduct;
use App\Models\MeterReading;
use App\Models\Nozzle;
use App\Models\User;
use App\Services\MeterReadingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->user = User::factory()->staff()->create();
    $this->nozzle = Nozzle::factory()->for(FuelProduct::factory())->create(['current_meter' => 1000]);
});

test('entries are blocked when the computer clock is wound back', function () {
    Carbon::setTestNow('2026-10-02 18:00:00');
    app(MeterReadingService::class)->record($this->nozzle, today(), Shift::Evening, 1000, 1500);

    // Someone sets the PC date back to post into yesterday.
    Carbon::setTestNow('2026-10-01 18:00:00');

    $this->actingAs($this->user)
        ->post(route('meter-readings.store'), [
            'nozzle_id' => $this->nozzle->id,
            'shift' => 'evening',
            'opening_meter' => '1500',
            'closing_meter' => '1800',
        ])
        ->assertSessionHasErrors('clock');

    expect(MeterReading::count())->toBe(1);
});

test('expenses and purchases are blocked by the same clock check', function () {
    Carbon::setTestNow('2026-10-02 18:00:00');
    app(MeterReadingService::class)->record($this->nozzle, today(), Shift::Evening, 1000, 1500);
    Carbon::setTestNow('2026-09-30 09:00:00');

    $this->actingAs($this->user)
        ->post(route('expenses.store'), ['category' => 'other', 'description' => 'Test', 'amount' => '100', 'payment_method' => 'cash'])
        ->assertSessionHasErrors('clock');

    $this->actingAs($this->user)
        ->post(route('purchases.store'), ['fuel_product_id' => $this->nozzle->fuel_product_id, 'quantity' => '100', 'rate' => '270'])
        ->assertSessionHasErrors('clock');
});

test('normal forward time passes the check', function () {
    Carbon::setTestNow('2026-10-02 08:00:00');
    app(MeterReadingService::class)->record($this->nozzle, today(), Shift::Morning, 1000, 1500);
    Carbon::setTestNow('2026-10-02 15:00:00');

    $this->actingAs($this->user)
        ->post(route('meter-readings.store'), [
            'nozzle_id' => $this->nozzle->id,
            'shift' => 'evening',
            'opening_meter' => '1500',
            'closing_meter' => '1800',
        ])
        ->assertSessionHasNoErrors();
});
