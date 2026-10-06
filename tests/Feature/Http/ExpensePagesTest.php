<?php

use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->user = User::factory()->staff()->create();
});

test('an expense can be recorded', function () {
    $this->actingAs($this->user)
        ->post(route('expenses.store'), [
            'category' => 'generator',
            'description' => 'Generator Fuel',
            'amount' => '5000',
            'payment_method' => 'cash',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Expense saved: Generator Fuel · Rs. 5,000');

    $expense = Expense::sole();

    expect($expense->business_date->isToday())->toBeTrue()
        ->and($expense->category)->toBe(ExpenseCategory::Generator)
        ->and($expense->payment_method)->toBe(PaymentMethod::Cash)
        ->and($expense->recorded_by)->toBe($this->user->id);
});

test('expenses are validated', function () {
    $this->actingAs($this->user)
        ->post(route('expenses.store'), [
            'category' => 'party',
            'description' => '',
            'amount' => '-5',
            'payment_method' => 'crypto',
        ])
        ->assertSessionHasErrors(['category', 'description', 'amount', 'payment_method']);
});

test('the expenses page lists and totals the period', function () {
    Expense::factory()->create(['description' => 'Tea', 'amount' => 800, 'category' => ExpenseCategory::Office]);
    Expense::factory()->create(['description' => 'Old bill', 'amount' => 999, 'business_date' => today()->subDays(5)]);

    $this->actingAs($this->user)
        ->get(route('expenses.index'))
        ->assertOk()
        ->assertSee(['Tea', 'Rs. 800', 'Office'])
        ->assertDontSee('Old bill');
});
