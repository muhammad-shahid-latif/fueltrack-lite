<?php

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\FuelProduct;
use App\Models\Supplier;
use App\Models\Tank;
use App\Models\User;
use App\Services\TankService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
    $this->owner = User::firstWhere('email', 'owner@alnoor.com');
    $this->staff = User::firstWhere('email', 'staff@alnoor.com');
});

test('staff record a fuel credit sale and a payment from the customer page', function () {
    $customer = Customer::firstWhere('name', 'City School Vans');
    $petrol = FuelProduct::firstWhere('code', 'petrol');
    $before = (float) $customer->credits()->sum('amount');

    $this->actingAs($this->staff)
        ->post(route('customers.credits.store', $customer), ['fuel_product_id' => $petrol->id, 'quantity' => '40', 'vehicle_number' => 'les-778'])
        ->assertRedirect(route('customers.show', $customer))
        ->assertSessionHas('success');

    $credit = $customer->credits()->latest('id')->first();

    expect((float) $customer->credits()->sum('amount'))->toBe(round($before + 40 * (float) $petrol->selling_price, 2))
        ->and($credit->vehicle_number)->toBe('LES-778')
        ->and($credit->recorded_by)->toBe($this->staff->id)
        ->and($credit->business_date->isToday())->toBeTrue();

    $this->actingAs($this->staff)
        ->post(route('customers.payments.store', $customer), ['amount' => '1000', 'payment_method' => 'cash'])
        ->assertSessionHas('success');
});

test('only the owner pays suppliers', function () {
    $supplier = Supplier::firstWhere('name', 'Indus Fuel Logistics');

    $this->actingAs($this->staff)
        ->post(route('suppliers.payments.store', $supplier), ['amount' => '1000', 'payment_method' => 'cash'])
        ->assertForbidden();

    $this->actingAs($this->owner)
        ->post(route('suppliers.payments.store', $supplier), ['amount' => '1000', 'payment_method' => 'cash', 'reference' => 'R-1'])
        ->assertRedirect(route('suppliers.show', $supplier))
        ->assertSessionHas('success');

    expect($supplier->payments()->latest('id')->first()->reference)->toBe('R-1');
});

test('a delivery with a supplier creates a bill with a due date and an on-the-spot payment', function () {
    $supplier = Supplier::firstWhere('name', 'Bahawalpur Oil Depot');
    $diesel = FuelProduct::firstWhere('code', 'diesel');
    $tank = Tank::firstWhere('name', 'Tank 3 · Diesel');
    $paidBefore = (float) $supplier->payments()->sum('amount');

    $this->actingAs($this->owner)
        ->post(route('purchases.store'), [
            'fuel_product_id' => $diesel->id,
            'tank_id' => $tank->id,
            'quantity' => '1000',
            'rate' => '280',
            'supplier_id' => $supplier->id,
            'paid_now' => '100000',
            'payment_method' => 'bank',
        ])
        ->assertRedirect(route('purchases.index'));

    $purchase = $supplier->purchases()->latest('id')->first();

    expect($purchase->due_date->toDateString())->toBe(today()->addDays(7)->toDateString())
        ->and($purchase->supplier_name)->toBe('Bahawalpur Oil Depot')
        ->and((float) $supplier->payments()->sum('amount'))->toBe($paidBefore + 100000);
});

test('the owner corrects a misc register entry and the audit trail keeps old and new values', function () {
    $expense = Expense::query()->whereDate('business_date', today())->where('category', 'generator')->first();

    $this->actingAs($this->owner)
        ->put(route('expenses.update', $expense), [
            'category' => 'generator',
            'description' => 'Generator diesel (corrected)',
            'amount' => '4321',
            'payment_method' => 'cash',
        ])
        ->assertSessionHas('success');

    $log = ActivityLog::query()->where('subject_type', Expense::class)->where('subject_id', $expense->id)->where('event', 'updated')->first();

    expect($log->user_id)->toBe($this->owner->id)
        ->and((float) $log->changes['amount']['new'])->toBe(4321.0)
        ->and($log->changes['description']['new'])->toBe('Generator diesel (corrected)');

    $this->actingAs($this->owner)->get(route('activity.index'))->assertOk()->assertSee('Generator diesel (corrected)');
});

test('payroll entries cannot be edited from the misc register', function () {
    $salary = Expense::query()->where('category', 'salary')->first();

    $this->actingAs($this->owner)->get(route('expenses.edit', $salary))->assertRedirect(route('payroll.index'));
    $this->actingAs($this->owner)->delete(route('expenses.destroy', $salary))->assertSessionHasErrors('record');
});

test('closed days keep their misc register entries locked', function () {
    $old = Expense::query()->whereDate('business_date', today()->subDays(2))->where('category', 'cleaning')->first();

    $this->actingAs($this->owner)->delete(route('expenses.destroy', $old))->assertSessionHasErrors('record');
    expect(Expense::find($old->id))->not->toBeNull();
});

test('the misc register filters by group', function () {
    $this->actingAs($this->owner)
        ->get(route('expenses.index', ['from' => today()->subDays(9)->toDateString(), 'to' => today()->toDateString(), 'group' => 'Utilities']))
        ->assertOk()
        ->assertSee('Electricity bill')
        ->assertDontSee('Forecourt cleaning');
});

test('the owner transfers fuel between tanks from the tank page', function () {
    $from = Tank::firstWhere('name', 'Tank 1 · Petrol');
    $to = Tank::firstWhere('name', 'Tank 2 · Petrol');
    $tanks = app(TankService::class);
    [$fromBefore, $toBefore] = [$tanks->level($from), $tanks->level($to)];

    $this->actingAs($this->owner)
        ->post(route('tanks.transfer', $from), ['to_tank_id' => $to->id, 'quantity' => '124'])
        ->assertRedirect(route('tanks.show', $from))
        ->assertSessionHas('transferred', true);

    expect($tanks->level($from))->toBe(round($fromBefore - 124, 2))
        ->and($tanks->level($to))->toBe(round($toBefore + 124, 2));

    $this->actingAs($this->owner)
        ->post(route('tanks.transfer', $from), ['to_tank_id' => $to->id, 'quantity' => '999999'])
        ->assertSessionHasErrors('quantity');
});
