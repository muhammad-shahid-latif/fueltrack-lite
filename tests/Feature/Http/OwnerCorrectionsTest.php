<?php

use App\Enums\Shift;
use App\Models\DailyClosing;
use App\Models\FuelProduct;
use App\Models\MeterReading;
use App\Models\Nozzle;
use App\Models\Sale;
use App\Models\User;
use App\Services\DailyClosingService;
use App\Services\FuelStockService;
use App\Services\MeterReadingService;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

    $this->owner = User::factory()->owner()->create();
    $this->staff = User::factory()->staff()->create();

    $this->petrol = FuelProduct::factory()->create(['name' => 'Petrol', 'code' => 'petrol', 'selling_price' => 280]);
    $this->nozzle = Nozzle::factory()->for($this->petrol)->create(['current_meter' => 1000]);
    $this->oil = FuelProduct::factory()->lubricant()->create(['opening_stock' => 20, 'selling_price' => 1850]);

    $this->read = fn (float $opening, float $closing, $date = null) => app(MeterReadingService::class)->record(
        nozzle: $this->nozzle,
        date: $date ?? today(),
        shift: Shift::Morning,
        openingMeter: $opening,
        closingMeter: $closing,
        recordedBy: $this->staff,
    );

    $this->edit = fn (array $overrides = []) => array_merge([
        'shift' => 'evening',
        'opening_meter' => '1000',
        'closing_meter' => '1150',
        'notes' => 'Meter typo fixed',
    ], $overrides);

    $this->stockOf = fn (FuelProduct $product) => app(FuelStockService::class)->currentStock($product, today());
});

test('only the owner can edit or delete readings, sales and closings', function () {
    $reading = ($this->read)(1000, 1100, today()->subDay());
    $sale = app(SaleService::class)->recordCounterSale($this->oil, today(), 2, $this->staff);
    $closing = app(DailyClosingService::class)->close(today()->subDay(), 0, 28000);

    $this->actingAs($this->staff);

    $this->get(route('meter-readings.edit', $reading))->assertForbidden();
    $this->put(route('meter-readings.update', $reading), ($this->edit)())->assertForbidden();
    $this->delete(route('meter-readings.destroy', $reading))->assertForbidden();
    $this->get(route('sales.edit', $sale))->assertForbidden();
    $this->delete(route('sales.destroy', $sale))->assertForbidden();
    $this->get(route('closings.edit', $closing))->assertForbidden();
    $this->delete(route('closings.destroy', $closing))->assertForbidden();

    expect(MeterReading::count())->toBe(1)
        ->and(Sale::count())->toBe(2)
        ->and(DailyClosing::count())->toBe(1);
});

test('editing a reading recalculates its sale at the rate it was sold at', function () {
    $reading = ($this->read)(1000, 1100);
    $this->petrol->update(['selling_price' => 300]);

    $this->actingAs($this->owner)
        ->put(route('meter-readings.update', $reading), ($this->edit)())
        ->assertRedirect(route('meter-readings.index', ['date' => today()->toDateString()]))
        ->assertSessionHas('success', 'Reading updated: 150 L × Rs. 280 = Rs. 42,000');

    $reading->refresh()->load('sale');

    expect($reading->shift)->toBe(Shift::Evening)
        ->and($reading->notes)->toBe('Meter typo fixed')
        ->and((float) $reading->liters)->toBe(150.0)
        ->and((float) $reading->sale->amount)->toBe(42000.0)
        ->and((float) $this->nozzle->fresh()->current_meter)->toBe(1150.0);
});

test('a changed closing meter carries into the next reading', function () {
    $first = ($this->read)(1000, 1100);
    $second = ($this->read)(1100, 1200);

    $this->actingAs($this->owner)
        ->put(route('meter-readings.update', $first), ($this->edit)(['closing_meter' => '1080']))
        ->assertSessionHasNoErrors();

    $second->refresh()->load('sale');

    expect((float) $second->opening_meter)->toBe(1080.0)
        ->and((float) $second->liters)->toBe(120.0)
        ->and((float) $second->sale->amount)->toBe(33600.0)
        ->and((float) $this->nozzle->fresh()->current_meter)->toBe(1200.0);
});

test('the meter chain cannot be broken by an edit', function () {
    $first = ($this->read)(1000, 1100);
    $second = ($this->read)(1100, 1200);

    $this->actingAs($this->owner);

    $this->put(route('meter-readings.update', $first), ($this->edit)(['closing_meter' => '1200']))
        ->assertSessionHasErrors('closing_meter');

    $this->put(route('meter-readings.update', $second), ($this->edit)(['opening_meter' => '1090', 'closing_meter' => '1200']))
        ->assertSessionHasErrors('opening_meter');

    expect((float) $first->fresh()->closing_meter)->toBe(1100.0)
        ->and((float) $second->fresh()->opening_meter)->toBe(1100.0);
});

test('deleting the latest reading removes its sale and rewinds the nozzle meter', function () {
    ($this->read)(1000, 1100);
    $latest = ($this->read)(1100, 1250);

    $this->actingAs($this->owner)
        ->delete(route('meter-readings.destroy', $latest))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(MeterReading::count())->toBe(1)
        ->and(Sale::count())->toBe(1)
        ->and((float) $this->nozzle->fresh()->current_meter)->toBe(1100.0);
});

test('an older reading cannot be deleted while later ones exist', function () {
    $first = ($this->read)(1000, 1100);
    ($this->read)(1100, 1200);

    $this->actingAs($this->owner)
        ->delete(route('meter-readings.destroy', $first))
        ->assertSessionHasErrors('record');

    expect(MeterReading::count())->toBe(2)
        ->and((float) $this->nozzle->fresh()->current_meter)->toBe(1200.0);
});

test('readings of a closed day stay locked until the day is reopened', function () {
    $reading = ($this->read)(1000, 1100, today()->subDay());
    $closing = app(DailyClosingService::class)->close(today()->subDay(), 0, 28000);

    $this->actingAs($this->owner);

    $this->get(route('meter-readings.edit', $reading))
        ->assertRedirect(route('meter-readings.index', ['date' => today()->subDay()->toDateString()]))
        ->assertSessionHas('warning');

    $this->put(route('meter-readings.update', $reading), ($this->edit)())
        ->assertSessionHasErrors('business_date');

    $this->delete(route('closings.destroy', $closing))
        ->assertRedirect(route('meter-readings.index', ['date' => today()->subDay()->toDateString()]));

    expect(DailyClosing::count())->toBe(0);

    $this->put(route('meter-readings.update', $reading), ($this->edit)())
        ->assertSessionHasNoErrors();

    expect((float) $reading->fresh()->closing_meter)->toBe(1150.0);
});

test('a counter sale can be corrected and stock follows', function () {
    $sale = app(SaleService::class)->recordCounterSale($this->oil, today(), 5, $this->staff);

    $this->actingAs($this->owner)
        ->get(route('sales.edit', $sale))
        ->assertOk()
        ->assertSee(['Edit counter sale', 'Up to 20 bottles available']);

    $this->actingAs($this->owner)
        ->put(route('sales.update', $sale), ['quantity' => '8', 'rate' => '1900', 'notes' => 'Two cartons'])
        ->assertRedirect(route('sales.index', ['source' => 'counter']))
        ->assertSessionHas('success', 'Counter sale updated: 8 bottles × Rs. 1,900 = Rs. 15,200');

    expect((float) $sale->fresh()->amount)->toBe(15200.0)
        ->and(($this->stockOf)($this->oil))->toBe(12.0);

    $this->actingAs($this->owner)
        ->put(route('sales.update', $sale), ['quantity' => '21', 'rate' => '1900'])
        ->assertSessionHasErrors('quantity');

    $this->actingAs($this->owner)
        ->delete(route('sales.destroy', $sale))
        ->assertSessionHas('success', 'Counter sale deleted. 8 bottles returned to '.$this->oil->name.' stock.');

    expect(Sale::count())->toBe(0)
        ->and(($this->stockOf)($this->oil))->toBe(20.0);
});

test('fuel sales are corrected through their meter reading', function () {
    $reading = ($this->read)(1000, 1100);
    $sale = $reading->sale;

    $this->actingAs($this->owner)
        ->get(route('sales.edit', $sale))
        ->assertRedirect(route('meter-readings.edit', $reading));

    $this->actingAs($this->owner)
        ->put(route('sales.update', $sale), ['quantity' => '1', 'rate' => '1'])
        ->assertSessionHasErrors('record');

    $this->actingAs($this->owner)
        ->delete(route('sales.destroy', $sale))
        ->assertSessionHasErrors('record');

    expect((float) $sale->fresh()->amount)->toBe(28000.0);
});

test('editing a closing recalculates the cash difference', function () {
    ($this->read)(1000, 1100, today()->subDay());
    $closing = app(DailyClosingService::class)->close(today()->subDay(), 10000, 30000);

    expect((float) $closing->difference)->toBe(-8000.0);

    $this->actingAs($this->owner)
        ->get(route('closings.edit', $closing))
        ->assertOk()
        ->assertSee(['Save cash count', 'Day stays closed']);

    $this->actingAs($this->owner)
        ->put(route('closings.update', $closing), [
            'opening_cash' => '10000',
            'card_sales' => '5000',
            'credit_sales' => '0',
            'actual_cash' => '33000',
            'notes' => 'Card slip found',
        ])
        ->assertRedirect(route('closings.show', $closing))
        ->assertSessionHas('success');

    $closing->refresh();

    expect((float) $closing->cash_sales)->toBe(23000.0)
        ->and((float) $closing->expected_cash)->toBe(33000.0)
        ->and((float) $closing->difference)->toBe(0.0)
        ->and($closing->notes)->toBe('Card slip found');
});

test('the owner sees edit actions on the lists and the edit pages render', function () {
    $reading = ($this->read)(1000, 1100);
    $sale = app(SaleService::class)->recordCounterSale($this->oil, today(), 1, $this->staff);

    $this->actingAs($this->owner)
        ->get(route('meter-readings.index'))
        ->assertOk()
        ->assertSee(route('meter-readings.edit', $reading));

    $this->actingAs($this->owner)
        ->get(route('sales.index'))
        ->assertOk()
        ->assertSee([route('sales.edit', $sale), route('meter-readings.edit', $reading)]);

    $this->actingAs($this->owner)
        ->get(route('meter-readings.edit', $reading))
        ->assertOk()
        ->assertSee(['Edit meter reading', 'Rate sold at', 'Delete reading']);

    $this->actingAs($this->staff)
        ->get(route('meter-readings.index'))
        ->assertOk()
        ->assertDontSee(route('meter-readings.edit', $reading));
});
