<?php

use App\Models\DailyClosing;
use App\Models\User;
use App\Services\DailyClosingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed();
    $this->staff = User::firstWhere('email', 'staff@alnoor.com');
});

test('the closing form shows today\'s summary', function () {
    $this->actingAs($this->staff)
        ->get(route('closings.create'))
        ->assertOk()
        ->assertSee(['Total sales', 'Counter items', 'Expected cash', 'Close day']);
});

test('closing today reconciles cash and locks the day', function () {
    $this->actingAs($this->staff)
        ->post(route('closings.store'), [
            'business_date' => today()->toDateString(),
            'opening_cash' => '100000',
            'card_sales' => '50000',
            'credit_sales' => '20000',
            'actual_cash' => '0',
        ])
        ->assertRedirect()
        ->assertSessionHas('warning');

    $closing = DailyClosing::query()->whereDate('business_date', today())->sole();

    expect($closing->closed_by)->toBe($this->staff->id)
        ->and((float) $closing->difference)->toBe(round(0 - (float) $closing->expected_cash, 2));

    $this->actingAs($this->staff)
        ->get(route('closings.show', $closing))
        ->assertOk()
        ->assertSee('Cash short');

    $this->actingAs($this->staff)
        ->get(route('closings.create'))
        ->assertRedirect(route('closings.show', $closing));
});

test('a balanced closing shows a success message', function () {
    $this->actingAs($this->staff)
        ->post(route('closings.store'), [
            'business_date' => today()->toDateString(),
            'opening_cash' => '0',
            'card_sales' => '0',
            'credit_sales' => '0',
            'actual_cash' => '0',
        ]);

    $closing = DailyClosing::query()->whereDate('business_date', today())->sole();
    $closing->delete();

    $this->actingAs($this->staff)
        ->post(route('closings.store'), [
            'business_date' => today()->toDateString(),
            'opening_cash' => '0',
            'card_sales' => '0',
            'credit_sales' => '0',
            'actual_cash' => (string) $closing->expected_cash,
        ])
        ->assertSessionHas('success', 'Day closed. Cash matches exactly.');
});

test('a future day cannot be closed', function () {
    $this->actingAs($this->staff)
        ->post(route('closings.store'), [
            'business_date' => today()->addDay()->toDateString(),
            'opening_cash' => '0',
            'card_sales' => '0',
            'credit_sales' => '0',
            'actual_cash' => '0',
        ])
        ->assertSessionHasErrors('business_date');
});

test('closing a day twice is rejected', function () {
    $this->actingAs($this->staff)
        ->post(route('closings.store'), [
            'business_date' => today()->subDay()->toDateString(),
            'opening_cash' => '0',
            'card_sales' => '0',
            'credit_sales' => '0',
            'actual_cash' => '0',
        ])
        ->assertSessionHasErrors('business_date');
});

test('card and credit above total sales are rejected', function () {
    $this->actingAs($this->staff)
        ->post(route('closings.store'), [
            'business_date' => today()->toDateString(),
            'opening_cash' => '0',
            'card_sales' => '99999999',
            'credit_sales' => '0',
            'actual_cash' => '0',
        ])
        ->assertSessionHasErrors('card_sales');
});

test('the closings list flags cash differences', function () {
    $short = DailyClosing::query()->orderBy('business_date')->first();
    $short->update(['actual_cash' => (float) $short->expected_cash - 1234, 'difference' => -1234]);

    $this->actingAs($this->staff)
        ->get(route('closings.index'))
        ->assertOk()
        ->assertSee('-Rs. 1,234');
});

test('expenses cannot be added once today is closed', function () {
    DailyClosing::query()->whereDate('business_date', today())->delete();
    app(DailyClosingService::class)->close(today(), openingCash: 0, actualCash: 0);

    $this->actingAs($this->staff)
        ->post(route('expenses.store'), [
            'category' => 'other',
            'description' => 'Late entry',
            'amount' => '100',
            'payment_method' => 'cash',
        ])
        ->assertSessionHasErrors('business_date');
});
