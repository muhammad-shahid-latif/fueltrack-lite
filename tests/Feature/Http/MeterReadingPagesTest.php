<?php

use App\Models\FuelProduct;
use App\Models\MeterReading;
use App\Models\Nozzle;
use App\Models\User;
use App\Services\DailyClosingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

    $this->user = User::factory()->staff()->create();
    $this->nozzle = Nozzle::factory()
        ->for(FuelProduct::factory()->state(['name' => 'Petrol', 'selling_price' => 280]))
        ->create(['current_meter' => 125430.50]);

    $this->reading = fn (array $overrides = []) => array_merge([
        'nozzle_id' => $this->nozzle->id,
        'shift' => 'evening',
        'opening_meter' => '125430.50',
        'closing_meter' => '126240.50',
    ], $overrides);
});

test('saving a reading creates the sale and shows the calculation', function () {
    $this->actingAs($this->user)
        ->post(route('meter-readings.store'), ($this->reading)(['notes' => 'Evening shift']))
        ->assertRedirect(route('meter-readings.index'))
        ->assertSessionHas('success', 'Evening shift reading saved: 810 L × Rs. 280 = Rs. 226,800');

    $reading = MeterReading::with('sale')->sole();

    expect($reading->recorded_by)->toBe($this->user->id)
        ->and($reading->shift->value)->toBe('evening')
        ->and((float) $reading->sale->amount)->toBe(226800.0);
});

test('the business date always comes from the server, never the form', function () {
    Carbon::setTestNow('2026-10-02 18:30:00');

    $this->actingAs($this->user)
        ->post(route('meter-readings.store'), ($this->reading)(['business_date' => '2026-09-01']));

    expect(MeterReading::sole()->business_date->toDateString())->toBe('2026-10-02');
});

test('the readings page lists the day and can filter by shift', function () {
    $this->actingAs($this->user)->post(route('meter-readings.store'), ($this->reading)());

    $this->actingAs($this->user)
        ->get(route('meter-readings.index'))
        ->assertOk()
        ->assertSee(['126240.50', '810 L', 'Rs. 226,800', 'Evening']);

    $this->actingAs($this->user)
        ->get(route('meter-readings.index', ['shift' => 'night']))
        ->assertOk()
        ->assertDontSee('126240.50');
});

test('the create form shows the shift picker, server date and opening meter', function () {
    Carbon::setTestNow('2026-10-02 07:15:00');

    $this->actingAs($this->user)
        ->get(route('meter-readings.create'))
        ->assertOk()
        ->assertSee(['125430.50', 'Morning', 'Evening', 'Night', 'Friday, 02 Oct 2026', 'set by server'])
        ->assertDontSee('name="business_date"', false);
});

test('a shift must be chosen from the list', function () {
    $this->actingAs($this->user)
        ->post(route('meter-readings.store'), ($this->reading)(['shift' => 'afternoon']))
        ->assertSessionHasErrors('shift');
});

test('closing meter must exceed opening meter', function () {
    $this->actingAs($this->user)
        ->from(route('meter-readings.create'))
        ->post(route('meter-readings.store'), ($this->reading)(['closing_meter' => '125000']))
        ->assertRedirect(route('meter-readings.create'))
        ->assertSessionHasErrors('closing_meter');

    expect(MeterReading::count())->toBe(0);
});

test('a tampered opening meter is rejected', function () {
    $this->actingAs($this->user)
        ->post(route('meter-readings.store'), ($this->reading)(['opening_meter' => '126000', 'closing_meter' => '126500']))
        ->assertSessionHasErrors('opening_meter');
});

test('readings cannot be saved once today is closed', function () {
    app(DailyClosingService::class)->close(today(), openingCash: 0, actualCash: 0);

    $this->actingAs($this->user)
        ->post(route('meter-readings.store'), ($this->reading)())
        ->assertSessionHasErrors('business_date');

    $this->actingAs($this->user)
        ->get(route('meter-readings.index'))
        ->assertSee('Closed')
        ->assertDontSee('New reading');
});

test('guests cannot record readings', function () {
    $this->post(route('meter-readings.store'))->assertRedirect(route('login'));
});
