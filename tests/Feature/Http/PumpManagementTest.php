<?php

use App\Models\FuelProduct;
use App\Models\Nozzle;
use App\Models\Pump;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed();
    $this->owner = User::firstWhere('email', 'owner@alnoor.com');
    $this->staff = User::firstWhere('email', 'staff@alnoor.com');
    $this->diesel = FuelProduct::firstWhere('code', 'diesel');
});

test('owners can add a pump and its nozzles', function () {
    $this->actingAs($this->owner)
        ->get(route('pumps.create'))
        ->assertOk()
        ->assertSee('Pump 04');

    $this->actingAs($this->owner)->post(route('pumps.store'), ['name' => 'Pump 04'])->assertRedirect();
    $pump = Pump::firstWhere('name', 'Pump 04');

    $this->actingAs($this->owner)
        ->post(route('pumps.nozzles.store', $pump), ['fuel_product_id' => $this->diesel->id, 'opening_meter' => '50000.25'])
        ->assertRedirect(route('pumps.edit', $pump));
    $this->actingAs($this->owner)
        ->post(route('pumps.nozzles.store', $pump), ['fuel_product_id' => $this->diesel->id, 'opening_meter' => '100']);

    expect($pump->nozzles()->pluck('number')->all())->toBe([1, 2])
        ->and((float) $pump->nozzles()->first()->current_meter)->toBe(50000.25);

    expect(readingFormNozzles($this->staff))->toContain('Pump 04 · Nozzle 1');
});

test('a pump taken out of service disappears from the reading form', function () {
    $pump = Pump::firstWhere('name', 'Pump 02');

    $this->actingAs($this->owner)
        ->put(route('pumps.update', $pump), ['name' => 'Pump 02', 'is_active' => '0'])
        ->assertRedirect(route('pumps.edit', $pump));

    expect(readingFormNozzles($this->staff))
        ->toContain('Pump 01 · Nozzle 1')
        ->not->toContain('Pump 02 · Nozzle 1', 'Pump 02 · Nozzle 2');
});

test('pumps and nozzles with readings cannot be deleted', function () {
    $pump = Pump::firstWhere('name', 'Pump 01');
    $nozzle = $pump->nozzles()->first();

    $this->actingAs($this->owner)->delete(route('pumps.destroy', $pump))->assertSessionHas('danger');
    $this->actingAs($this->owner)->delete(route('pumps.nozzles.destroy', [$pump, $nozzle]))->assertSessionHas('danger');

    expect($pump->fresh())->not->toBeNull()->and($nozzle->fresh())->not->toBeNull();
});

test('a new pump without readings can be removed', function () {
    $pump = Pump::create(['name' => 'Spare Pump']);
    $pump->nozzles()->create(['number' => 1, 'fuel_product_id' => $this->diesel->id]);

    $this->actingAs($this->owner)->delete(route('pumps.destroy', $pump))->assertRedirect(route('pumps.index'));

    expect(Pump::find($pump->id))->toBeNull()
        ->and(Nozzle::query()->where('pump_id', $pump->id)->count())->toBe(0);
});

test('nozzles can only be linked to fuel and keep their meter on update', function () {
    $pump = Pump::firstWhere('name', 'Pump 03');
    $nozzle = $pump->nozzles()->first();
    $oil = FuelProduct::firstWhere('code', 'engine-oil-1l');

    $this->actingAs($this->owner)
        ->put(route('pumps.nozzles.update', [$pump, $nozzle]), ['fuel_product_id' => $oil->id, 'is_active' => '1'])
        ->assertSessionHasErrors('fuel_product_id');

    $this->actingAs($this->owner)
        ->put(route('pumps.nozzles.update', [$pump, $nozzle]), ['fuel_product_id' => $this->diesel->id, 'is_active' => '1', 'opening_meter' => '1'])
        ->assertSessionHasErrors('opening_meter');
});

test('a nozzle from another pump cannot be edited through this pump', function () {
    $nozzle = Pump::firstWhere('name', 'Pump 01')->nozzles()->first();

    $this->actingAs($this->owner)
        ->put(route('pumps.nozzles.update', [Pump::firstWhere('name', 'Pump 02'), $nozzle]), ['fuel_product_id' => $this->diesel->id])
        ->assertNotFound();
});

test('staff can view pumps but not manage them', function () {
    $this->actingAs($this->staff)->get(route('pumps.index'))->assertOk()->assertDontSee(route('pumps.create'));
    $this->actingAs($this->staff)->get(route('pumps.create'))->assertForbidden();
    $this->actingAs($this->staff)->post(route('pumps.store'), ['name' => 'X'])->assertForbidden();
});

/**
 * Nozzle labels offered on the reading form. Read from the view data, since
 * the page passes them to Alpine as JSON (where "·" may be escaped).
 *
 * @return list<string>
 */
function readingFormNozzles(User $user): array
{
    return test()->actingAs($user)
        ->get(route('meter-readings.create'))
        ->assertOk()
        ->viewData('nozzles')
        ->pluck('label')
        ->all();
}

test('a pump can be added with four nozzles in one step', function () {
    $petrol = FuelProduct::firstWhere('code', 'petrol');
    $diesel = $this->diesel;
    $petrolTank = \App\Models\Tank::firstWhere('fuel_product_id', $petrol->id);

    $this->actingAs($this->owner)
        ->post(route('pumps.store'), [
            'name' => 'Pump 09',
            'nozzles' => [
                ['fuel_product_id' => $petrol->id, 'tank_id' => $petrolTank->id, 'opening_meter' => '1000'],
                ['fuel_product_id' => $petrol->id, 'tank_id' => '', 'opening_meter' => '2000'],
                ['fuel_product_id' => $diesel->id, 'tank_id' => '', 'opening_meter' => '3000'],
                ['fuel_product_id' => $diesel->id, 'tank_id' => '', 'opening_meter' => '4000.5'],
            ],
        ])
        ->assertSessionHas('success', fn (string $m) => str_contains($m, 'with 4 nozzles'));

    $pump = Pump::firstWhere('name', 'Pump 09');

    expect($pump->nozzles()->orderBy('number')->pluck('number')->all())->toBe([1, 2, 3, 4])
        ->and((float) $pump->nozzles()->where('number', 4)->value('current_meter'))->toBe(4000.5)
        ->and($pump->nozzles()->where('number', 1)->value('tank_id'))->toBe($petrolTank->id);

    // All four show on the reading form.
    $this->actingAs($this->owner)->get(route('meter-readings.create'))->assertOk()->assertSee('Pump 09');
});

test('a nozzle cannot draw from a tank of another fuel', function () {
    $petrolTank = \App\Models\Tank::firstWhere('fuel_product_id', FuelProduct::firstWhere('code', 'petrol')->id);

    $this->actingAs($this->owner)
        ->post(route('pumps.store'), [
            'name' => 'Pump 10',
            'nozzles' => [['fuel_product_id' => $this->diesel->id, 'tank_id' => $petrolTank->id, 'opening_meter' => '10']],
        ])
        ->assertSessionHasErrors('nozzles.0.tank_id');

    expect(Pump::firstWhere('name', 'Pump 10'))->toBeNull();
});
