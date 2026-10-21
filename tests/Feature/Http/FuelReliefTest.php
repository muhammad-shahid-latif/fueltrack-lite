<?php

use App\Models\DailyClosing;
use App\Models\FuelProduct;
use App\Models\ReliefSale;
use App\Models\User;
use App\Services\DailyClosingService;
use App\Services\ReliefService;
use App\Support\BusinessClock;
use App\Support\ReliefScheme;
use App\Support\StaffAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
    $this->owner = User::firstWhere('email', 'owner@alnoor.com');
    $this->staff = User::firstWhere('email', 'staff@alnoor.com');
    $this->petrol = FuelProduct::firstWhere('code', 'petrol');
    $this->price = (float) $this->petrol->selling_price;
    $this->today = BusinessClock::today()->toDateString();
    DailyClosing::query()->whereDate('business_date', $this->today)->delete();

    $this->day = fn (array $overrides = []) => [
        'business_date' => $this->today,
        'fuel_product_id' => $this->petrol->id,
        'tokens' => '60',
        'amount' => '30000',
        ...$overrides,
    ];
});

function reliefDay(int $tokens = 1, ?float $amount = null): ReliefSale
{
    return app(ReliefService::class)->saveDay(BusinessClock::today(), FuelProduct::firstWhere('code', 'petrol'), $tokens, $amount ?? $tokens * 500);
}

test('staff enter the day tokens and relief amount', function () {
    $this->actingAs($this->staff)
        ->post(route('relief.store'), ($this->day)())
        ->assertRedirect(route('relief.index', ['date' => $this->today]))
        ->assertSessionHas('success', fn (string $message) => str_contains($message, '60 tokens, Rs. 30,000'));

    $entry = ReliefSale::sole();

    // Liters are the amount at the pump price.
    expect((float) $entry->relief_amount)->toBe(30000.0)
        ->and($entry->tokens)->toBe(60)
        ->and((float) $entry->relief_rate)->toBe(500.0)
        ->and((float) $entry->quantity)->toBe(round(30000 / $this->price, 2));

    $this->actingAs($this->staff)->get(route('relief.index'))
        ->assertOk()
        ->assertSee('Tokens redeemed')
        ->assertSee('Meters today')
        ->assertDontSee('Hi-Octane')
        ->assertDontSee('Scheme setting');
});

test('the amount is what the government system shows, not tokens times the rate', function () {
    $this->actingAs($this->staff)->post(route('relief.store'), ($this->day)(['tokens' => '3', 'amount' => '1400']))->assertSessionHasNoErrors();

    expect((float) ReliefSale::sole()->relief_amount)->toBe(1400.0);
});

test('saving again replaces the day total', function () {
    $this->actingAs($this->staff)->post(route('relief.store'), ($this->day)());
    $this->actingAs($this->staff)->post(route('relief.store'), ($this->day)(['tokens' => '70', 'amount' => '35000']))->assertSessionHasNoErrors();

    expect(ReliefSale::count())->toBe(1)->and((float) ReliefSale::sole()->relief_amount)->toBe(35000.0);
});

test('tokens and amount are required', function () {
    $this->actingAs($this->staff)
        ->post(route('relief.store'), ($this->day)(['tokens' => '', 'amount' => '0']))
        ->assertSessionHasErrors(['tokens', 'amount']);
});

test('relief can be saved before the meter readings are in', function () {
    \App\Models\Sale::query()->whereDate('business_date', $this->today)->delete();

    $this->actingAs($this->staff)->post(route('relief.store'), ($this->day)(['tokens' => '1', 'amount' => '500']))->assertSessionHasNoErrors();
});

test('saving from the closing returns to the closing', function () {
    $this->actingAs($this->staff)
        ->post(route('relief.store'), ($this->day)(['return' => 'closing']))
        ->assertRedirect(route('closings.create', ['date' => $this->today]));
});

test('the closing shows the relief and takes it off the expected cash', function () {
    $service = app(DailyClosingService::class);
    $before = $service->summary($this->today);

    reliefDay(60, 30000);

    expect($service->summary($this->today)['relief'])->toBe(['tokens' => 60, 'liters' => round(30000 / $this->price, 2), 'amount' => 30000.0]);

    $this->actingAs($this->staff)->get(route('closings.create'))
        ->assertOk()
        ->assertSee('PM Fuel Relief · 60 tokens')
        ->assertSee('Relief amount, paid by the government')
        ->assertSee('of which on relief');

    $closing = $service->close($this->today, 0, 0, 0, $this->owner);

    expect((float) $closing->cash_sales)->toBe(round($before['total_sales'] - $before['credit_dispensed'] - 30000, 2))
        ->and((float) $closing->relief_amount)->toBe(30000.0);

    $this->actingAs($this->owner)->get(route('closings.show', $closing))
        ->assertOk()
        ->assertSee('PM Fuel Relief')
        ->assertSee('Fuel relief (government pays)');
});

test('the closing asks for relief when none is entered', function () {
    $this->actingAs($this->staff)->get(route('closings.create'))
        ->assertOk()
        ->assertSee('Sold petrol on PM Fuel Relief today?');
});

test('relief cannot change on a closed day', function () {
    app(DailyClosingService::class)->close($this->today, 0, 0, 0, $this->owner);

    $this->actingAs($this->staff)->post(route('relief.store'), ($this->day)())->assertSessionHasErrors('business_date');
});

test('the owner marks a day received and can undo it', function () {
    $entry = reliefDay(1);

    $this->actingAs($this->owner)
        ->post(route('relief.receive', $this->today), ['received_on' => $this->today, 'reference' => 'SBP-77'])
        ->assertSessionHas('success');

    expect(ReliefService::pendingTotal())->toBe(0.0)->and($entry->fresh()->receipt_reference)->toBe('SBP-77');

    // A received day cannot be changed until the receipt is undone.
    $this->actingAs($this->staff)->post(route('relief.store'), ($this->day)(['tokens' => '2', 'amount' => '1000']))->assertSessionHasErrors('amount');
    $this->actingAs($this->staff)->delete(route('relief.destroy', $entry))->assertSessionHasErrors('record');

    $this->actingAs($this->owner)->delete(route('relief.unreceive', $this->today))->assertSessionHas('success');
    expect(ReliefService::pendingTotal())->toBe(500.0);

    $this->actingAs($this->staff)->delete(route('relief.destroy', $entry))->assertSessionHas('success');
    expect(ReliefSale::count())->toBe(0);
});

test('only the owner tracks claims and changes the relief per token', function () {
    reliefDay(1);

    $this->actingAs($this->staff)->post(route('relief.receive', $this->today), ['received_on' => $this->today])->assertForbidden();
    $this->actingAs($this->staff)->put(route('relief.settings'), ['per_token' => '400'])->assertForbidden();

    $this->actingAs($this->owner)->put(route('relief.settings'), ['per_token' => '600'])->assertSessionHas('success');

    expect(ReliefScheme::perToken())->toBe(600.0);

    $this->actingAs($this->owner)->get(route('relief.index'))->assertOk()->assertSee('Scheme setting');
});

test('the owner can switch fuel relief off for the manager', function () {
    StaffAccess::save(['relief']);

    $this->actingAs($this->staff)->get(route('relief.index'))->assertRedirect(route('dashboard'));
    $this->actingAs($this->staff)->get(route('closings.create'))->assertOk()->assertDontSee('Sold petrol on PM Fuel Relief today?');
});
