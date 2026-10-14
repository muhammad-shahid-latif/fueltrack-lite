<?php

use App\Enums\Shift;
use App\Models\Customer;
use App\Models\FuelProduct;
use App\Models\Nozzle;
use App\Services\CreditService;
use App\Services\DailyClosingService;
use App\Services\MeterReadingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->credits = app(CreditService::class);
    $this->closings = app(DailyClosingService::class);
    $this->meters = app(MeterReadingService::class);
    $this->petrol = FuelProduct::factory()->create(['name' => 'Petrol', 'selling_price' => 280]);
    $this->nozzle = Nozzle::factory()->for($this->petrol)->create();
    $this->customer = Customer::create(['name' => 'Al-Madina Transport']);
});

/** A 20 L slip where only 10 L goes into the vehicle today. */
function splitSlip($test)
{
    return $test->credits->recordCredit(
        customer: $test->customer,
        product: $test->petrol,
        quantity: 20,
        amount: null,
        slipNumber: 'S-1',
        owedQuantity: 10,
    );
}

test('the customer is billed for the whole slip while half is owed', function () {
    $credit = splitSlip($this);

    expect((float) $credit->amount)->toBe(5600.0)
        ->and($credit->givenQuantity())->toBe(10.0)
        ->and($credit->owedRemaining())->toBe(10.0)
        ->and($credit->owedValue())->toBe(2800.0)
        ->and($this->credits->balance($this->customer))->toBe(5600.0)
        ->and($this->credits->openOwed()->pluck('id')->all())->toBe([$credit->id]);
});

test('owed liters cannot be more than the slip or set without fuel', function () {
    expect(fn () => $this->credits->recordCredit($this->customer, $this->petrol, 20, null, owedQuantity: 25))
        ->toThrow(ValidationException::class, 'between 0 and the liters on the slip');

    expect(fn () => $this->credits->recordCredit($this->customer, null, null, 1000, owedQuantity: 5))
        ->toThrow(ValidationException::class);
});

test('the closing does not count owed fuel as missing cash on the slip day', function () {
    // The nozzle pumped 10 L (Rs. 2,800), all of it for the credit customer.
    $this->meters->record($this->nozzle, today(), Shift::Morning, 0, 10);
    splitSlip($this);

    $summary = $this->closings->summary(today());

    expect($summary['credit_sales'])->toBe(5600.0)
        ->and($summary['owed_fuel_billed'])->toBe(2800.0)
        ->and($summary['credit_dispensed'])->toBe(2800.0)
        ->and($summary['owed_liters']['billed'])->toBe(10.0);

    // No cash came in, and none is expected: no false "cash excess".
    $closing = $this->closings->close(today(), openingCash: 0, actualCash: 0);

    expect((float) $closing->cash_sales)->toBe(0.0)
        ->and((float) $closing->expected_cash)->toBe(0.0)
        ->and((float) $closing->difference)->toBe(0.0)
        ->and((float) $closing->owed_fuel_billed)->toBe(2800.0);
});

test('giving the owed fuel the next day is credit, not a cash shortage', function () {
    Carbon::setTestNow(now()->subDay());
    $credit = splitSlip($this);
    Carbon::setTestNow();

    // Next day: the nozzle pumps the remaining 10 L and nobody pays cash for it.
    $this->meters->record($this->nozzle, today(), Shift::Morning, 0, 10);
    $delivery = $this->credits->giveOwedFuel($credit, 10);

    expect((float) $delivery->amount)->toBe(2800.0)
        ->and($credit->fresh()->owedRemaining())->toBe(0.0)
        ->and($this->credits->openOwed())->toBeEmpty()
        ->and($this->credits->balance($this->customer))->toBe(5600.0);

    $closing = $this->closings->close(today(), openingCash: 0, actualCash: 0);

    expect((float) $closing->owed_fuel_given)->toBe(2800.0)
        ->and((float) $closing->expected_cash)->toBe(0.0)
        ->and((float) $closing->difference)->toBe(0.0);
});

test('owed fuel can be given in parts and the parts add up to the slip', function () {
    $credit = $this->credits->recordCredit($this->customer, $this->petrol, 10, null, owedQuantity: 3.33);

    $first = $this->credits->giveOwedFuel($credit, 1.11);
    $last = $this->credits->giveOwedFuel($credit, 2.22);

    expect(round((float) $first->amount + (float) $last->amount, 2))->toBe($credit->owedValue())
        ->and($credit->fresh()->owedRemaining())->toBe(0.0);

    expect(fn () => $this->credits->giveOwedFuel($credit, 1))->toThrow(ValidationException::class, 'already been given');
});

test('more than the owed liters cannot be given', function () {
    $credit = splitSlip($this);

    expect(fn () => $this->credits->giveOwedFuel($credit, 10.5))->toThrow(ValidationException::class, 'Enter up to 10.00 L');
});

test('a slip with owed fuel already given cannot be deleted until the delivery is removed', function () {
    $credit = splitSlip($this);
    $delivery = $this->credits->giveOwedFuel($credit, 4);

    expect(fn () => $this->credits->deleteCredit($credit))->toThrow(ValidationException::class, 'Delete those deliveries first');

    $this->credits->deleteOwedDelivery($delivery);
    $this->credits->deleteCredit($credit->fresh());

    expect($this->customer->credits()->count())->toBe(0);
});

test('owed fuel cannot be given on a closed day', function () {
    $this->meters->record($this->nozzle, today(), Shift::Morning, 0, 10);
    $credit = splitSlip($this);
    $this->closings->close(today(), openingCash: 0, actualCash: 0);

    $this->credits->giveOwedFuel($credit, 5);
})->throws(ValidationException::class, 'already closed');

test('the customer ledger shows the owed delivery without changing the balance', function () {
    $credit = splitSlip($this);
    $this->credits->giveOwedFuel($credit, 10, notes: 'Came back at night');

    $ledger = $this->credits->account($this->customer)['ledger'];
    $owedRow = $ledger->firstWhere('type', 'owed');

    expect($owedRow['label'])->toContain('Owed fuel given · 10.00 L Petrol')
        ->and($owedRow['detail'])->toContain('Slip S-1')
        ->and($ledger->last()['balance'])->toBe(5600.0)
        ->and($ledger->firstWhere('type', 'credit')['detail'])->toContain('10.00 L given, 10.00 L owed');
});
