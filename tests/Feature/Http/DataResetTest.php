<?php

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\DailyClosing;
use App\Models\FuelProduct;
use App\Models\MeterReading;
use App\Models\Nozzle;
use App\Models\Pump;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\Tank;
use App\Models\User;
use App\Services\FuelStockService;
use App\Services\TankService;
use App\Support\BusinessClock;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
    $this->owner = User::firstWhere('email', 'owner@alnoor.com');
    $this->staff = User::firstWhere('email', 'staff@alnoor.com');
});

test('the owner sees the reset section in settings', function () {
    $this->actingAs($this->owner)->get(route('settings.edit'))->assertOk()->assertSee('Reset data')->assertSee('Erase everything (fresh start)');
});

test('erasing records keeps the station setup with its stock and meters', function () {
    $tank = Tank::query()->first();
    $petrol = FuelProduct::firstWhere('code', 'petrol');
    $nozzle = Nozzle::query()->first();
    $level = app(TankService::class)->level($tank, BusinessClock::today());
    $stock = app(FuelStockService::class)->currentStock($petrol, BusinessClock::today());
    $meter = (float) $nozzle->current_meter;

    $this->actingAs($this->owner)
        ->post(route('settings.reset'), ['scope' => 'records', 'confirm' => 'RESET', 'password' => 'Demo@12345'])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('success');

    expect(Sale::count())->toBe(0)
        ->and(MeterReading::count())->toBe(0)
        ->and(DailyClosing::count())->toBe(0)
        ->and(Customer::count())->toBe(0)
        ->and(Pump::count())->toBeGreaterThan(0)
        ->and(FuelProduct::count())->toBeGreaterThan(0)
        // Levels and meters do not jump.
        ->and(app(TankService::class)->level($tank->fresh(), BusinessClock::today()))->toBe(max(0, $level))
        ->and(app(FuelStockService::class)->currentStock($petrol->fresh(), BusinessClock::today()))->toBe(max(0, $stock))
        ->and((float) $nozzle->fresh()->current_meter)->toBe($meter)
        // Logins and settings stay; the reset itself is the first audit line.
        ->and(User::count())->toBe(2)
        ->and(Setting::get('station_name'))->not->toBeNull()
        ->and(ActivityLog::sole()->event)->toBe('reset');
});

test('erasing everything leaves only logins and settings', function () {
    $this->actingAs($this->owner)
        ->post(route('settings.reset'), ['scope' => 'everything', 'confirm' => 'RESET', 'password' => 'Demo@12345'])
        ->assertRedirect(route('fuel-products.index'));

    expect(FuelProduct::count())->toBe(0)
        ->and(Pump::count())->toBe(0)
        ->and(Tank::count())->toBe(0)
        ->and(User::count())->toBe(2);

    // The app keeps working on an empty station.
    foreach (['dashboard', 'meter-readings.index', 'meter-readings.create', 'sales.index', 'credits.index', 'closings.create', 'tanks.index', 'stock.index', 'pumps.index', 'pumps.create', 'relief.index', 'reports.daily', 'fuel-products.index', 'settings.edit'] as $route) {
        $this->actingAs($this->owner)->get(route($route))->assertOk();
    }
});

test('a reset needs the word RESET and the right password', function () {
    $this->actingAs($this->owner)
        ->post(route('settings.reset'), ['scope' => 'everything', 'confirm' => 'reset', 'password' => 'Demo@12345'])
        ->assertSessionHasErrors('confirm');

    $this->actingAs($this->owner)
        ->post(route('settings.reset'), ['scope' => 'everything', 'confirm' => 'RESET', 'password' => 'wrong'])
        ->assertSessionHasErrors('password');

    expect(Sale::count())->toBeGreaterThan(0);
});

test('only the owner can reset', function () {
    $this->actingAs($this->staff)
        ->post(route('settings.reset'), ['scope' => 'everything', 'confirm' => 'RESET', 'password' => 'Demo@12345'])
        ->assertForbidden();

    expect(Sale::count())->toBeGreaterThan(0);
});
