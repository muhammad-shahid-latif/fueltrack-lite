<?php

use App\Models\FuelProduct;
use App\Models\FuelPurchase;
use App\Models\Tank;
use App\Models\User;
use App\Services\DailyClosingService;
use App\Services\FuelStockService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed();
    $this->staff = User::firstWhere('email', 'staff@alnoor.com');
    $this->petrol = FuelProduct::firstWhere('code', 'petrol');
    $this->tank = Tank::firstWhere('name', 'Tank 1 · Petrol');
});

test('stock page shows fuel and counter items', function () {
    $this->actingAs($this->staff)
        ->get(route('stock.index'))
        ->assertOk()
        ->assertSee(['Petrol', 'Diesel', 'Hi-Octane', 'Engine Oil 1L', 'Received', 'Closing']);
});

test('recording a purchase raises stock and uses the server date', function () {
    $stock = app(FuelStockService::class);
    $before = $stock->currentStock($this->petrol, today());

    $this->actingAs($this->staff)
        ->post(route('purchases.store'), [
            'fuel_product_id' => $this->petrol->id,
            'business_date' => today()->subDays(3)->toDateString(),
            'quantity' => '10000',
            'rate' => '270',
            'supplier_name' => 'Depot',
            'tank_id' => $this->tank->id,
        ])
        ->assertRedirect(route('purchases.index'))
        ->assertSessionHas('success', fn (string $message) => str_starts_with($message, 'Purchase saved: 10,000 L of Petrol for Rs. 2,700,000. Stock updated; average cost now'));

    $purchase = FuelPurchase::latest('id')->first();

    expect($stock->currentStock($this->petrol, today()))->toBe(round($before + 10000, 2))
        ->and($purchase->business_date->isToday())->toBeTrue()
        ->and($purchase->recorded_by)->toBe($this->staff->id)
        ->and($purchase->tank_id)->toBe($this->tank->id);
});

test('purchases are validated', function () {
    $this->actingAs($this->staff)
        ->post(route('purchases.store'), ['fuel_product_id' => $this->petrol->id, 'quantity' => '0', 'rate' => ''])
        ->assertSessionHasErrors(['quantity', 'rate']);
});

test('purchases cannot be recorded once today is closed', function () {
    app(DailyClosingService::class)->close(today(), openingCash: 0, actualCash: 0);

    $this->actingAs($this->staff)
        ->post(route('purchases.store'), ['fuel_product_id' => $this->petrol->id, 'quantity' => '1000', 'rate' => '270'])
        ->assertSessionHasErrors('business_date');
});

test('purchases page lists recent deliveries', function () {
    $this->actingAs($this->staff)
        ->get(route('purchases.index'))
        ->assertOk()
        ->assertSee('Bahawalpur Oil Depot');
});
