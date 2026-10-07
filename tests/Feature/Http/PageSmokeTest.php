<?php

use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Tank;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed());

test('every owner page renders with demo data', function () {
    $owner = User::firstWhere('email', 'owner@alnoor.com');
    $tank = Tank::first();
    $supplier = Supplier::first();
    $customer = Customer::first();

    $pages = [
        route('dashboard'), route('tanks.index'), route('tanks.show', $tank), route('tanks.create'), route('tanks.edit', $tank),
        route('stock.movements'), route('stock.index'), route('nozzles.index'), route('reports.nozzles'),
        route('suppliers.index'), route('suppliers.show', $supplier), route('suppliers.create'), route('suppliers.edit', $supplier),
        route('customers.index'), route('customers.show', $customer), route('customers.create'), route('customers.edit', $customer),
        route('credits.index'), route('credits.create'), route('expenses.index'), route('expenses.create'),
        route('payroll.report'), route('activity.index'), route('purchases.create'), route('purchases.index'),
        route('sales.create'), route('sales.index'), route('closings.create'), route('pumps.edit', 1), route('payroll.index'),
        route('reports.profit-loss'), route('reports.daily'), route('meter-readings.index'),
    ];

    foreach ($pages as $url) {
        $this->actingAs($owner)->get($url)->assertOk();
    }

    $this->actingAs($owner)->getJson(route('tanks.levels'))->assertOk()->assertJsonCount(4, 'tanks');
    $this->actingAs($owner)->getJson(route('nozzles.live'))->assertOk()->assertJsonCount(6, 'nozzles');
});

test('staff see operations but not owner registers', function () {
    $staff = User::firstWhere('email', 'staff@alnoor.com');

    foreach ([route('tanks.index'), route('nozzles.index'), route('credits.create'), route('customers.index'), route('customers.show', Customer::first()), route('stock.movements')] as $url) {
        $this->actingAs($staff)->get($url)->assertOk();
    }

    foreach ([route('suppliers.index'), route('activity.index'), route('payroll.report'), route('tanks.create'), route('customers.create')] as $url) {
        $this->actingAs($staff)->get($url)->assertForbidden();
    }
});
