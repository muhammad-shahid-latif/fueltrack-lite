<?php

use App\Models\Customer;
use App\Models\User;
use App\Support\Navigation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
    $this->owner = User::firstWhere('email', 'owner@alnoor.com');
    $this->staff = User::firstWhere('email', 'staff@alnoor.com');
});

test('the sidebar groups related pages into sections', function () {
    $labels = collect(Navigation::for($this->owner))->flatMap(fn ($group) => array_column($group['items'], 'label'))->all();

    expect($labels)->toContain('Khata register', 'Meter readings', 'Stock & deliveries', 'Staff & payroll', 'Reports', 'Setup')
        ->not->toContain('Debt register', 'Customers', 'Suppliers', 'Nozzle meters', 'Inventory movements', 'Payroll report');
});

test('the khata register shows entries and customers as tabs; suppliers sit with deliveries', function () {
    $this->actingAs($this->owner)->get(route('credits.index'))
        ->assertOk()
        ->assertSee(['Khata register', 'Customers owe in total', 'Receive payment', 'New customer'])
        ->assertSeeInOrder(['section-tab', 'Entries', 'Customers'], false)
        ->assertDontSee(route('suppliers.index'));

    $this->actingAs($this->owner)->get(route('suppliers.index'))
        ->assertOk()
        ->assertSeeInOrder(['section-tab', 'Stock', 'Movements', 'Deliveries', 'Suppliers'], false);

    // The Khata entry stays highlighted on the customer pages.
    $this->actingAs($this->owner)->get(route('customers.index'))
        ->assertOk()
        ->assertSee(['Search name, vehicle or phone', 'Owes money', 'id="newCustomerModal"'], false);
});

test('staff see the khata without supplier and owner-only buttons', function () {
    $this->actingAs($this->staff)->get(route('credits.index'))
        ->assertOk()
        ->assertSee(['Entries', 'Customers', 'Receive payment'])
        ->assertDontSee(['New customer', route('suppliers.index')]);
});

test('a new customer added from the popup opens on their khata', function () {
    $this->actingAs($this->owner)
        ->from(route('credits.index'))
        ->post(route('customers.store'), ['_modal' => 'customer', 'name' => 'Bismillah Goods', 'phone' => '0300-1234567', 'opening_balance' => '1,500'])
        ->assertRedirect(route('customers.show', Customer::firstWhere('name', 'Bismillah Goods')))
        ->assertSessionHas('success', 'Bismillah Goods added to the Khata register.');

    expect((float) Customer::firstWhere('name', 'Bismillah Goods')->opening_balance)->toBe(1500.0);
});

test('a failed popup save comes back with the popup reopened', function () {
    $this->actingAs($this->owner)
        ->from(route('customers.index'))
        ->post(route('customers.store'), ['_modal' => 'customer', 'name' => ''])
        ->assertRedirect(route('customers.index'))
        ->assertSessionHasErrors('name');

    $this->actingAs($this->owner)
        ->withSession(['_old_input' => ['_modal' => 'customer']])
        ->get(route('customers.index'))
        ->assertSee('x-init="true &&', false);
});

test('a payment is received from the khata popup', function () {
    $customer = Customer::firstWhere('name', 'City School Vans');

    $this->actingAs($this->staff)
        ->from(route('credits.index'))
        ->post(route('customers.payments.store', $customer), ['_modal' => 'payment', 'customer_ref' => $customer->id, 'amount' => '1,000', 'payment_method' => 'cash'])
        ->assertRedirect(route('customers.show', $customer))
        ->assertSessionHas('success');
});
