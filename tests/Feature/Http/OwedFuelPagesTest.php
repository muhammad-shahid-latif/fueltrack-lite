<?php

use App\Models\Customer;
use App\Models\CustomerCredit;
use App\Models\FuelProduct;
use App\Models\User;
use App\Services\CreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
    $this->owner = User::firstWhere('email', 'owner@alnoor.com');
    $this->staff = User::firstWhere('email', 'staff@alnoor.com');
    $this->customer = Customer::firstWhere('name', 'City School Vans');
    $this->petrol = FuelProduct::firstWhere('code', 'petrol');
});

test('staff save a slip with part of the fuel given later', function () {
    $this->actingAs($this->staff)
        ->post(route('credits.store'), [
            'customer_id' => $this->customer->id,
            'fuel_product_id' => $this->petrol->id,
            'quantity' => '20',
            'given_quantity' => '10',
            'slip_number' => 'T-55',
        ])
        ->assertRedirect(route('customers.show', $this->customer))
        ->assertSessionHas('success', fn (string $message) => str_contains($message, '10 L still to give later'));

    $credit = CustomerCredit::latest('id')->first();

    expect((float) $credit->quantity)->toBe(20.0)
        ->and((float) $credit->owed_quantity)->toBe(10.0)
        ->and((float) $credit->amount)->toBe(round(20 * (float) $this->petrol->selling_price, 2));
});

test('a slip without the split owes nothing', function () {
    $this->actingAs($this->staff)
        ->post(route('customers.credits.store', $this->customer), ['fuel_product_id' => $this->petrol->id, 'quantity' => '15'])
        ->assertSessionHas('success');

    expect((float) CustomerCredit::latest('id')->first()->owed_quantity)->toBe(0.0);
});

test('given now cannot be more than the slip', function () {
    $this->actingAs($this->staff)
        ->post(route('customers.credits.store', $this->customer), ['fuel_product_id' => $this->petrol->id, 'quantity' => '15', 'given_quantity' => '20'])
        ->assertSessionHasErrors('given_quantity');
});

test('owed fuel shows on the debt register and customer page and can be given from there', function () {
    $credit = app(CreditService::class)->recordCredit($this->customer, $this->petrol, 20, null, slipNumber: 'T-77', owedQuantity: 10);

    $this->actingAs($this->staff)->get(route('credits.index'))
        ->assertOk()
        ->assertSee('Fuel still to give')
        ->assertSee('10 L still to give');

    $this->actingAs($this->staff)->get(route('customers.show', $this->customer))
        ->assertOk()
        ->assertSee('Fuel still to give')
        ->assertSee('Slip T-77');

    $this->actingAs($this->staff)
        ->from(route('credits.index'))
        ->post(route('credits.owed.store', $credit), ['give_quantity' => '6'])
        ->assertRedirect(route('credits.index'))
        ->assertSessionHas('success', fn (string $message) => str_contains($message, '4 L still owed'));

    $this->actingAs($this->staff)
        ->from(route('credits.index'))
        ->post(route('credits.owed.store', $credit), ['give_quantity' => '5'])
        ->assertSessionHasErrors('give_quantity');

    expect($credit->fresh()->owedRemaining())->toBe(4.0);
});

test('the closing page explains credit billed but not pumped yet', function () {
    app(CreditService::class)->recordCredit($this->customer, $this->petrol, 20, null, owedQuantity: 10);

    $this->actingAs($this->owner)->get(route('closings.create'))
        ->assertOk()
        ->assertSee('Credit slips with fuel given on another day')
        ->assertSee('Billed today, fuel still to give (10 L)');
});

test('only the owner deletes an owed fuel delivery', function () {
    $credits = app(CreditService::class);
    $delivery = $credits->giveOwedFuel($credits->recordCredit($this->customer, $this->petrol, 20, null, owedQuantity: 10), 10);

    $this->actingAs($this->staff)->delete(route('owed-fuel.destroy', $delivery))->assertForbidden();

    $this->actingAs($this->owner)
        ->from(route('customers.show', $this->customer))
        ->delete(route('owed-fuel.destroy', $delivery))
        ->assertSessionHas('success');

    expect($delivery->credit->fresh()->owedRemaining())->toBe(10.0);
});
