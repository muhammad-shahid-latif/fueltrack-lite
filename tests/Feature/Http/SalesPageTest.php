<?php

use App\Models\FuelProduct;
use App\Models\Sale;
use App\Models\User;
use App\Services\FuelStockService;
use App\Support\Format;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed();
    $this->user = User::firstWhere('email', 'staff@alnoor.com');
});

test('all sales across every date are shown by default', function () {
    $this->actingAs($this->user)
        ->get(route('sales.index'))
        ->assertOk()
        ->assertSee(['All dates', today()->format('d M Y')])
        ->assertSee(Format::money(round(Sale::sum('amount'))))
        ->assertSee(number_format(Sale::count()).' entries');

    // The oldest day is on the last page, so nothing is filtered out.
    $this->actingAs($this->user)
        ->get(route('sales.index', ['page' => (int) ceil(Sale::count() / 30)]))
        ->assertSee(today()->subDays(9)->format('d M Y'));
});

test('sales can be filtered by date, product and source', function () {
    $diesel = FuelProduct::firstWhere('code', 'diesel');

    $this->actingAs($this->user)
        ->get(route('sales.index', ['from' => today()->toDateString(), 'to' => today()->toDateString(), 'product' => $diesel->id]))
        ->assertOk()
        ->assertSee('Filtered period')
        ->assertDontSee(today()->subDay()->format('d M Y'));

    $this->actingAs($this->user)
        ->get(route('sales.index', ['source' => 'counter']))
        ->assertOk()
        ->assertSee('Counter')
        ->assertDontSee('Pump 01 · Nozzle 1');
});

test('an inverted date range is rejected', function () {
    $this->actingAs($this->user)
        ->get(route('sales.index', ['from' => today()->toDateString(), 'to' => today()->subDay()->toDateString()]))
        ->assertSessionHasErrors('to');
});

test('a counter sale reduces stock and is priced from the product', function () {
    $oil = FuelProduct::firstWhere('code', 'engine-oil-1l');
    $before = app(FuelStockService::class)->currentStock($oil, today());

    $this->actingAs($this->user)
        ->post(route('sales.store'), ['fuel_product_id' => $oil->id, 'quantity' => '2'])
        ->assertRedirect()
        ->assertSessionHas('success', 'Counter sale saved: 2 bottles × Rs. 1,850 = Rs. 3,700');

    expect(app(FuelStockService::class)->currentStock($oil, today()))->toBe($before - 2.0);
});

test('a counter sale cannot exceed stock', function () {
    $oil = FuelProduct::firstWhere('code', 'engine-oil-4l');

    $this->actingAs($this->user)
        ->post(route('sales.store'), ['fuel_product_id' => $oil->id, 'quantity' => '9999'])
        ->assertSessionHasErrors('quantity');
});

test('fuel cannot be sold at the counter', function () {
    $this->actingAs($this->user)
        ->post(route('sales.store'), ['fuel_product_id' => FuelProduct::firstWhere('code', 'petrol')->id, 'quantity' => '5'])
        ->assertSessionHasErrors('fuel_product_id');
});

test('the counter sale form lists only counter items', function () {
    $this->actingAs($this->user)
        ->get(route('sales.create'))
        ->assertOk()
        ->assertSee('Engine Oil 1L')
        ->assertDontSee('"name":"Petrol"', false);
});

test('sidebar links to every module', function () {
    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertSee([route('meter-readings.index'), route('sales.index'), route('pumps.index'), route('fuel-products.index')]);
});
