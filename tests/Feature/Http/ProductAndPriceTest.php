<?php

use App\Models\FuelPrice;
use App\Models\FuelProduct;
use App\Models\User;
use App\Support\StaffAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed();
    $this->owner = User::firstWhere('email', 'owner@alnoor.com');
    $this->staff = User::firstWhere('email', 'staff@alnoor.com');
    $this->petrol = FuelProduct::firstWhere('code', 'petrol');
});

test('a price change is stamped with the server time and kept in history', function () {
    Carbon::setTestNow(now()->setTime(23, 45));

    $this->actingAs($this->owner)
        ->put(route('fuel-products.price', $this->petrol), ['price' => '265.50', 'note' => 'OGRA notification'])
        ->assertRedirect(route('fuel-products.edit', $this->petrol))
        ->assertSessionHas('success', 'Petrol price changed to Rs. 265.50 from '.now()->format('d M Y').', 11:45 PM.');

    $entry = FuelPrice::query()->latest('id')->first();

    expect((float) $this->petrol->fresh()->selling_price)->toBe(265.5)
        ->and($entry->effective_at->format('Y-m-d H:i'))->toBe(now()->format('Y-m-d H:i'))
        ->and((float) $entry->previous_price)->toBe(280.0)
        ->and($entry->changed_by)->toBe($this->owner->id)
        ->and($entry->note)->toBe('OGRA notification');

    $this->actingAs($this->owner)
        ->get(route('fuel-products.edit', $this->petrol))
        ->assertSee(['Price history', 'OGRA notification', '11:45 PM', 'Rs. 265.50']);
});

test('setting the same price again is rejected', function () {
    $this->actingAs($this->owner)
        ->put(route('fuel-products.price', $this->petrol), ['price' => '280'])
        ->assertSessionHasErrors('price');
});

test('the dashboard price board shows when each price took effect', function () {
    $this->actingAs($this->staff)
        ->get(route('dashboard'))
        ->assertSee(['fuel prices', 'Since', '▲ 5']);
});

test('owners can add a counter item and it gets an opening price entry', function () {
    $this->actingAs($this->owner)
        ->post(route('fuel-products.store'), [
            'name' => 'Gear Oil',
            'type' => 'lubricant',
            'unit' => 'bottle',
            'selling_price' => '1200',
            'opening_stock' => '10',
            'low_stock_threshold' => '3',
        ])
        ->assertRedirect();

    $product = FuelProduct::firstWhere('name', 'Gear Oil');

    expect($product->code)->toBe('gear-oil')
        ->and($product->unit->value)->toBe('bottle')
        ->and($product->prices()->count())->toBe(1);
});

test('fuel products are always measured in liters', function () {
    $this->actingAs($this->owner)->post(route('fuel-products.store'), [
        'name' => 'Super Diesel',
        'type' => 'fuel',
        'unit' => 'bottle',
        'selling_price' => '305',
        'opening_stock' => '0',
        'low_stock_threshold' => '1000',
    ]);

    expect(FuelProduct::firstWhere('name', 'Super Diesel')->unit->value)->toBe('L');
});

test('owners can edit details without touching the price', function () {
    $this->actingAs($this->owner)
        ->put(route('fuel-products.update', $this->petrol), [
            'name' => 'Petrol (Super)',
            'unit' => 'L',
            'low_stock_threshold' => '3500',
            'is_active' => '1',
        ])
        ->assertRedirect(route('fuel-products.edit', $this->petrol));

    expect($this->petrol->fresh()->name)->toBe('Petrol (Super)')
        ->and((float) $this->petrol->fresh()->selling_price)->toBe(280.0);
});

test('products with history cannot be deleted, unused ones can', function () {
    $this->actingAs($this->owner)->delete(route('fuel-products.destroy', $this->petrol))->assertSessionHas('danger');
    expect($this->petrol->fresh())->not->toBeNull();

    $unused = FuelProduct::factory()->lubricant()->create();
    $this->actingAs($this->owner)->delete(route('fuel-products.destroy', $unused))->assertRedirect(route('fuel-products.index'));
    expect($unused->fresh())->toBeNull();
});

test('the manager changes prices but not product details', function () {
    $this->actingAs($this->staff)->get(route('fuel-products.index'))->assertOk()->assertSee(['Engine Oil 1L', 'Price since', 'Change price']);
    $this->actingAs($this->staff)->get(route('fuel-products.edit', $this->petrol))->assertForbidden();
    $this->actingAs($this->staff)->get(route('fuel-products.create'))->assertForbidden();

    $this->actingAs($this->staff)
        ->put(route('fuel-products.price', $this->petrol), ['price' => '282.50', 'note' => 'OGRA'])
        ->assertRedirect(route('fuel-products.index'))
        ->assertSessionHas('success');

    expect((float) $this->petrol->fresh()->selling_price)->toBe(282.5)
        ->and($this->petrol->prices()->latest('id')->first()->changed_by)->toBe($this->staff->id);
});

test('the owner can stop the manager from changing prices', function () {
    StaffAccess::save(['prices']);

    $this->actingAs($this->staff)->get(route('fuel-products.index'))->assertOk()->assertDontSee('Change price');
    $this->actingAs($this->staff)
        ->put(route('fuel-products.price', $this->petrol), ['price' => '1'])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('warning', 'The owner has turned off Change fuel prices for your account.');

    expect((float) $this->petrol->fresh()->selling_price)->not->toBe(1.0);

    // The owner is never restricted.
    $this->actingAs($this->owner)->put(route('fuel-products.price', $this->petrol), ['price' => '290'])->assertSessionHas('success');
});

test('purchases work for counter items too', function () {
    $oil = FuelProduct::firstWhere('code', 'brake-oil');

    $this->actingAs($this->staff)
        ->post(route('purchases.store'), ['fuel_product_id' => $oil->id, 'quantity' => '12', 'rate' => '500'])
        ->assertSessionHas('success', fn (string $message) => str_starts_with($message, 'Purchase saved: 12 bottles of Brake Oil for Rs. 6,000. Stock updated;'));
});
