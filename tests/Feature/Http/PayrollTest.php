<?php

use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Enums\SalarySlipStatus;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\SalarySlip;
use App\Models\User;
use App\Services\DailyClosingService;
use App\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-10-15 10:00:00');
    $this->withoutVite();

    $this->owner = User::factory()->owner()->create();
    $this->staff = User::factory()->staff()->create();

    $this->aslam = Employee::factory()->create(['name' => 'Muhammad Aslam', 'monthly_salary' => 31000, 'joined_on' => '2024-01-01']);
    $this->bilal = Employee::factory()->create(['name' => 'Bilal Ahmed', 'monthly_salary' => 28000, 'joined_on' => '2024-01-01']);

    $this->payroll = app(PayrollService::class);
});

afterEach(fn () => Carbon::setTestNow());

test('staff cannot open employees, payroll or profit and loss', function () {
    $this->actingAs($this->staff);

    $this->get(route('employees.index'))->assertForbidden();
    $this->get(route('employees.show', $this->aslam))->assertForbidden();
    $this->get(route('payroll.index'))->assertForbidden();
    $this->post(route('payroll.prepare'), ['month' => '2026-10'])->assertForbidden();
    $this->post(route('employees.advances.store', $this->aslam), ['amount' => 1000, 'payment_method' => 'cash'])->assertForbidden();
    $this->get(route('reports.profit-loss'))->assertForbidden();

    expect(SalarySlip::count())->toBe(0);
});

test('the owner can add, edit and deactivate an employee', function () {
    $this->actingAs($this->owner)
        ->post(route('employees.store'), [
            'name' => 'Waqas Khan',
            'designation' => 'Helper',
            'phone' => '0305-2167890',
            'joined_on' => '2026-10-10',
            'monthly_salary' => '22000',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Waqas Khan added to staff.');

    $waqas = Employee::firstWhere('name', 'Waqas Khan');
    expect($waqas->is_active)->toBeTrue()->and((float) $waqas->monthly_salary)->toBe(22000.0);

    $this->actingAs($this->owner)
        ->put(route('employees.update', $waqas), [
            'name' => 'Waqas Khan',
            'designation' => 'Pump attendant',
            'joined_on' => '2026-10-10',
            'monthly_salary' => '25000',
            'is_active' => '0',
        ])
        ->assertRedirect(route('employees.show', $waqas));

    expect($waqas->fresh())->designation->toBe('Pump attendant')->is_active->toBeFalse();

    $this->actingAs($this->owner)->get(route('employees.index'))->assertOk()->assertSee(['Waqas Khan', 'Inactive', 'Muhammad Aslam']);
});

test('an advance is an expense of today and is owed back', function () {
    $this->actingAs($this->owner)
        ->post(route('employees.advances.store', $this->bilal), ['amount' => '5000', 'payment_method' => 'cash', 'notes' => 'Family need'])
        ->assertRedirect(route('employees.show', $this->bilal))
        ->assertSessionHas('success');

    $expense = Expense::sole();

    expect($expense->category)->toBe(ExpenseCategory::StaffAdvance)
        ->and($expense->business_date->toDateString())->toBe('2026-10-15')
        ->and((float) $expense->amount)->toBe(5000.0)
        ->and($this->payroll->outstandingAdvance($this->bilal))->toBe(5000.0)
        ->and(app(DailyClosingService::class)->summary(today())['cash_expenses'])->toBe(5000.0);

    $this->actingAs($this->owner)
        ->get(route('employees.show', $this->bilal))
        ->assertOk()
        ->assertSee(['Advance to recover', 'Rs. 5,000', 'Family need']);
});

test('preparing a month fills basic salaries and deducts advances', function () {
    $this->payroll->giveAdvance($this->bilal, 5000);
    $newcomer = Employee::factory()->create(['name' => 'Waqas Khan', 'monthly_salary' => 31000, 'joined_on' => '2026-10-11']);
    Employee::factory()->inactive()->create(['name' => 'Left Already']);

    $this->actingAs($this->owner)
        ->post(route('payroll.prepare'), ['month' => '2026-10'])
        ->assertRedirect(route('payroll.index', ['month' => '2026-10']))
        ->assertSessionHas('success', 'Salary sheet for October 2026 prepared for 3 employees.');

    $slips = SalarySlip::with('employee')->get()->keyBy('employee.name');

    expect($slips->keys()->sort()->values()->all())->toBe(['Bilal Ahmed', 'Muhammad Aslam', 'Waqas Khan'])
        ->and((float) $slips['Bilal Ahmed']->advance_deduction)->toBe(5000.0)
        ->and((float) $slips['Bilal Ahmed']->net_salary)->toBe(23000.0)
        ->and((float) $slips['Waqas Khan']->absent_days)->toBe(10.0)
        ->and((float) $slips['Waqas Khan']->net_salary)->toBe(21000.0)
        ->and($slips['Muhammad Aslam']->days_in_month)->toBe(31);

    // Running it again adds nobody twice, and future months are refused.
    $this->actingAs($this->owner)->post(route('payroll.prepare'), ['month' => '2026-10'])->assertSessionHas('warning');
    $this->actingAs($this->owner)->post(route('payroll.prepare'), ['month' => '2026-11'])->assertSessionHasErrors('month');

    expect(SalarySlip::count())->toBe(3);
});

test('a slip can be adjusted within its limits', function () {
    $this->payroll->giveAdvance($this->aslam, 2000);
    $slip = $this->payroll->prepare('2026-10-01')->firstWhere('employee_id', $this->aslam->id);

    $this->actingAs($this->owner)
        ->put(route('payroll.slips.update', $slip), ['absent_days' => '2', 'bonus' => '3000', 'other_deduction' => '500', 'advance_deduction' => '1000', 'notes' => 'Eid bonus'])
        ->assertRedirect(route('payroll.index', ['month' => '2026-10']));

    $slip->refresh();

    // One day of a 31,000 salary in a 31-day month is 1,000.
    expect((float) $slip->absence_deduction)->toBe(2000.0)
        ->and((float) $slip->net_salary)->toBe(31000.0 - 2000 + 3000 - 500 - 1000)
        ->and($slip->notes)->toBe('Eid bonus');

    $this->actingAs($this->owner)
        ->put(route('payroll.slips.update', $slip), ['absent_days' => '0', 'bonus' => '0', 'other_deduction' => '0', 'advance_deduction' => '2500'])
        ->assertSessionHasErrors('advance_deduction');

    $this->actingAs($this->owner)
        ->put(route('payroll.slips.update', $slip), ['absent_days' => '0', 'bonus' => '0', 'other_deduction' => '40000', 'advance_deduction' => '0'])
        ->assertSessionHasErrors('other_deduction');
});

test('paying a salary records the expense and clears the advance', function () {
    $this->payroll->giveAdvance($this->bilal, 5000);
    $slip = $this->payroll->prepare('2026-10-01')->firstWhere('employee_id', $this->bilal->id);

    $this->actingAs($this->owner)
        ->post(route('payroll.slips.pay', $slip), ['payment_method' => 'cash'])
        ->assertRedirect(route('payroll.index', ['month' => '2026-10']))
        ->assertSessionHas('success', 'Bilal Ahmed paid Rs. 23,000 by cash.');

    $slip->refresh();
    $salary = Expense::query()->where('category', ExpenseCategory::Salary->value)->sole();

    expect($slip->status)->toBe(SalarySlipStatus::Paid)
        ->and($slip->expense_id)->toBe($salary->id)
        ->and((float) $salary->amount)->toBe(23000.0)
        ->and($salary->description)->toBe('Salary October 2026: Bilal Ahmed')
        ->and($this->payroll->outstandingAdvance($this->bilal))->toBe(0.0);

    $this->actingAs($this->owner)
        ->get(route('payroll.slips.show', $slip))
        ->assertOk()
        ->assertSee(['Salary Slip', 'Bilal Ahmed', 'Rs. 23,000', 'Advance recovered']);

    // A paid slip is locked until the payment is reversed.
    $this->actingAs($this->owner)
        ->put(route('payroll.slips.update', $slip), ['absent_days' => '0', 'bonus' => '0', 'other_deduction' => '0', 'advance_deduction' => '0'])
        ->assertSessionHasErrors('record');

    $this->actingAs($this->owner)
        ->delete(route('payroll.slips.reverse', $slip))
        ->assertSessionHas('success');

    expect($slip->fresh()->status)->toBe(SalarySlipStatus::Pending)
        ->and(Expense::query()->where('category', ExpenseCategory::Salary->value)->count())->toBe(0)
        ->and($this->payroll->outstandingAdvance($this->bilal))->toBe(5000.0);
});

test('pay all pays every pending slip of the month', function () {
    $this->payroll->prepare('2026-10-01');

    $this->actingAs($this->owner)
        ->post(route('payroll.pay-all'), ['month' => '2026-10', 'payment_method' => 'bank'])
        ->assertSessionHas('success', '2 salaries for October 2026 paid.');

    expect(SalarySlip::where('status', 'paid')->count())->toBe(2)
        ->and((float) Expense::sum('amount'))->toBe(59000.0)
        ->and(Expense::query()->distinct()->pluck('payment_method')->map->value->all())->toBe([PaymentMethod::Bank->value]);
});

test('salaries cannot be paid on a closed day', function () {
    $slip = $this->payroll->prepare('2026-10-01')->first();
    app(DailyClosingService::class)->close(today(), 0, 0);

    $this->actingAs($this->owner)
        ->post(route('payroll.slips.pay', $slip), ['payment_method' => 'cash'])
        ->assertSessionHasErrors('business_date');

    expect($slip->fresh()->status)->toBe(SalarySlipStatus::Pending);

    $this->actingAs($this->owner)->get(route('payroll.index'))->assertOk()->assertSee('Today is closed');
});

test('employees with salary records cannot be deleted', function () {
    $this->payroll->giveAdvance($this->aslam, 1000);

    $this->actingAs($this->owner)
        ->delete(route('employees.destroy', $this->aslam))
        ->assertSessionHas('danger');

    $this->actingAs($this->owner)
        ->delete(route('employees.destroy', $this->bilal))
        ->assertRedirect(route('employees.index'));

    expect(Employee::pluck('name')->all())->toBe(['Muhammad Aslam']);
});

test('salaries are only recorded through payroll, not the expense form', function () {
    $this->actingAs($this->owner)
        ->post(route('expenses.store'), ['category' => 'salary', 'description' => 'Salary', 'amount' => 1000, 'payment_method' => 'cash'])
        ->assertSessionHasErrors('category');

    $this->actingAs($this->owner)->get(route('expenses.create'))->assertOk()->assertDontSee('Staff advances');
});

test('the payroll page shows the month sheet', function () {
    $this->payroll->prepare('2026-10-01');

    $this->actingAs($this->owner)
        ->get(route('payroll.index'))
        ->assertOk()
        ->assertSee(['October 2026 salary sheet', 'Muhammad Aslam', 'Bilal Ahmed', 'Pay all pending', 'Rs. 59,000']);

    $this->actingAs($this->owner)
        ->get(route('payroll.index', ['month' => '2026-09']))
        ->assertOk()
        ->assertSee('Prepare salary sheet');
});

test('an advance given after the sheet is prepared is added to the unpaid slip', function () {
    $this->payroll->giveAdvance($this->aslam, 10000);
    $slip = $this->payroll->prepare('2026-10-01')->firstWhere('employee_id', $this->aslam->id);

    expect((float) $slip->advance_deduction)->toBe(10000.0);

    $this->actingAs($this->owner)
        ->post(route('employees.advances.store', $this->aslam), ['amount' => '1000', 'payment_method' => 'cash'])
        ->assertSessionHas('success', 'Advance of Rs. 1,000 given to Muhammad Aslam. Added to the October 2026 salary sheet: net salary is now Rs. 20,000.');

    $slip->refresh();

    expect((float) $slip->advance_deduction)->toBe(11000.0)
        ->and((float) $slip->net_salary)->toBe(20000.0)
        ->and($this->payroll->outstandingAdvance($this->aslam))->toBe(11000.0);

    // Deleting that advance takes it off the slip again.
    $this->payroll->deleteAdvance($this->aslam->advances()->latest('id')->first());

    expect((float) $slip->fresh()->advance_deduction)->toBe(10000.0)
        ->and((float) $slip->fresh()->net_salary)->toBe(21000.0);
});

test('the payroll page flags advances owed but not deducted', function () {
    $slip = $this->payroll->prepare('2026-10-01')->firstWhere('employee_id', $this->aslam->id);
    $this->payroll->update($slip, 0, 0, 0, 0);
    $this->payroll->giveAdvance($this->aslam, 2000);
    $this->payroll->update($slip->fresh(), 0, 0, 0, 500);

    $this->actingAs($this->owner)
        ->get(route('payroll.index'))
        ->assertSee('Rs. 1,500 more owed');
});
