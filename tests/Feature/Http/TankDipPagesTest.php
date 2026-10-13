<?php

use App\Models\Tank;
use App\Models\User;
use App\Services\DailyClosingService;
use App\Services\TankService;
use App\Support\Format;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
    $this->staff = User::firstWhere('email', 'staff@alnoor.com');
    $this->tank = Tank::firstWhere('name', 'Tank 1 · Petrol');
    $this->tanks = app(TankService::class);
});

test('every active tank card has a dip reading button and the shared popup', function () {
    $this->actingAs($this->staff)
        ->get(route('tanks.index'))
        ->assertOk()
        ->assertSee('Dip reading')
        ->assertSee(str_replace('/', '\\/', route('tanks.dips.index', $this->tank)), false)
        ->assertSee('id="dipModal"', false);
});

test('the popup reads the live system stock and recent dips as JSON', function () {
    $this->tanks->recordDip($this->tank, $this->tanks->level($this->tank) - 5, $this->staff);

    $this->actingAs($this->staff)
        ->getJson(route('tanks.dips.index', $this->tank))
        ->assertOk()
        ->assertJsonPath('tank.id', $this->tank->id)
        ->assertJsonPath('book', $this->tanks->level($this->tank))
        ->assertJsonPath('day_closed', false)
        ->assertJsonPath('history.0.status', 'loss')
        ->assertJsonPath('history.0.variance', -5);
});

test('a dip equal to the system stock is reported as matching', function () {
    $book = $this->tanks->level($this->tank);

    $this->actingAs($this->staff)
        ->postJson(route('tanks.dips.store', $this->tank), ['dip_reading' => $book])
        ->assertCreated()
        ->assertJsonPath('status', 'match')
        ->assertJsonPath('tank.level', $book)
        ->assertJsonPath('tank.last_dip.status', 'match')
        ->assertJsonFragment(['message' => sprintf('Readings match. %s dip equals the system stock of %s.', $this->tank->name, Format::liters($book))]);
});

test('a different dip reports the gain or loss and corrects the system stock', function () {
    $book = $this->tanks->level($this->tank);

    $this->actingAs($this->staff)
        ->postJson(route('tanks.dips.store', $this->tank), ['dip_reading' => number_format($book + 12.5, 2)])
        ->assertCreated()
        ->assertJsonPath('status', 'gain')
        ->assertJsonPath('dip.variance', 12.5)
        ->assertJsonPath('dip.variance_label', '+12.50 L')
        ->assertJsonPath('tank.level', round($book + 12.5, 2));

    expect($this->tanks->level($this->tank))->toBe(round($book + 12.5, 2));
});

test('dip errors come back as JSON for the popup', function () {
    $this->actingAs($this->staff)
        ->postJson(route('tanks.dips.store', $this->tank), ['dip_reading' => (float) $this->tank->capacity + 1])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('dip_reading');

    app(DailyClosingService::class)->close(today(), openingCash: 0, actualCash: 0);

    $this->actingAs($this->staff)
        ->getJson(route('tanks.dips.index', $this->tank))
        ->assertJsonPath('day_closed', true);

    $this->actingAs($this->staff)
        ->postJson(route('tanks.dips.store', $this->tank), ['dip_reading' => 100])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('business_date');
});

test('the classic form post still redirects back with a message', function () {
    $book = $this->tanks->level($this->tank);

    $this->actingAs($this->staff)
        ->from(route('tanks.show', $this->tank))
        ->post(route('tanks.dips.store', $this->tank), ['dip_reading' => $book - 3])
        ->assertRedirect(route('tanks.show', $this->tank))
        ->assertSessionHas('warning');
});

test('hover prefetches and background polling do not change where back() returns to', function () {
    $this->actingAs($this->staff)->get(route('tanks.show', $this->tank))->assertOk();

    $this->actingAs($this->staff)
        ->withHeaders(['X-Sec-Purpose' => 'prefetch'])
        ->get(route('dashboard'))
        ->assertOk();

    $this->actingAs($this->staff)
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->get(route('tanks.levels'))
        ->assertOk();

    $this->flushHeaders()
        ->actingAs($this->staff)
        ->post(route('tanks.dips.store', $this->tank), ['dip_reading' => ''])
        ->assertRedirect(route('tanks.show', $this->tank))
        ->assertSessionHasErrors('dip_reading');
});
