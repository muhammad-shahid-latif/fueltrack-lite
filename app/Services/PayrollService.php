<?php

namespace App\Services;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Enums\SalarySlipStatus;
use App\Models\Employee;
use App\Models\SalaryAdvance;
use App\Models\SalarySlip;
use App\Models\User;
use App\Support\BusinessClock;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Staff advances and monthly salaries.
 *
 * Every rupee paid to staff is also written as an expense on the day it is
 * paid, so it reduces that day's expected cash and appears in profit & loss.
 * Advances are recovered from the next salary that is paid.
 */
class PayrollService
{
    public function __construct(
        private ExpenseService $expenses,
        private DailyClosingService $closings,
    ) {}

    /**
     * @throws ValidationException
     */
    public function giveAdvance(
        Employee $employee,
        float $amount,
        PaymentMethod $paymentMethod = PaymentMethod::Cash,
        ?User $recordedBy = null,
        ?string $notes = null,
        DateTimeInterface|string|null $date = null,
    ): SalaryAdvance {
        if (! $employee->is_active) {
            throw ValidationException::withMessages(['amount' => "{$employee->name} is no longer active."]);
        }

        return DB::transaction(function () use ($employee, $amount, $paymentMethod, $recordedBy, $notes, $date) {
            $date = $date ? Carbon::parse($date)->startOfDay() : BusinessClock::today();

            $expense = $this->expenses->record(
                date: $date,
                category: ExpenseCategory::StaffAdvance,
                description: "Advance to {$employee->name}",
                amount: $amount,
                paymentMethod: $paymentMethod,
                recordedBy: $recordedBy,
                notes: $notes,
            );

            $advance = SalaryAdvance::create([
                'employee_id' => $employee->id,
                'business_date' => $date,
                'amount' => round($amount, 2),
                'expense_id' => $expense->id,
                'recorded_by' => $recordedBy?->id,
                'notes' => $notes,
            ]);

            $this->addToPendingSlip($employee, (float) $advance->amount);

            return $advance;
        });
    }

    /**
     * Removes an advance and its expense while its day is open and it has not
     * yet been recovered from a paid salary.
     *
     * @throws ValidationException
     */
    public function deleteAdvance(SalaryAdvance $advance): void
    {
        DB::transaction(function () use ($advance) {
            $advance = SalaryAdvance::query()->with(['employee', 'expense'])->lockForUpdate()->findOrFail($advance->id);
            $this->closings->assertOpen($advance->business_date);

            if ($this->outstandingAdvance($advance->employee) + 0.005 < (float) $advance->amount) {
                throw ValidationException::withMessages([
                    'record' => 'This advance has already been recovered from a paid salary, so it cannot be deleted.',
                ]);
            }

            $expense = $advance->expense;
            $advance->delete();
            $expense->delete();

            $this->trimPendingSlips($advance->employee);
        });
    }

    /**
     * The unpaid slip a new advance would be recovered from, if any.
     */
    public function pendingSlipFor(Employee $employee): ?SalarySlip
    {
        return SalarySlip::query()
            ->where('employee_id', $employee->id)
            ->where('status', SalarySlipStatus::Pending->value)
            ->orderByDesc('month')
            ->first();
    }

    /**
     * Advances given minus what paid salaries have already recovered.
     */
    public function outstandingAdvance(Employee $employee): float
    {
        return (float) ($this->outstandingAdvances(collect([$employee->id]))->get($employee->id) ?? 0.0);
    }

    /**
     * @param  Collection<int, int>  $employeeIds
     * @return Collection<int, float> keyed by employee id
     */
    public function outstandingAdvances(Collection $employeeIds): Collection
    {
        $given = SalaryAdvance::query()
            ->whereIn('employee_id', $employeeIds)
            ->groupBy('employee_id')
            ->selectRaw('employee_id, SUM(amount) as total')
            ->pluck('total', 'employee_id');

        $recovered = SalarySlip::query()
            ->whereIn('employee_id', $employeeIds)
            ->where('status', SalarySlipStatus::Paid->value)
            ->groupBy('employee_id')
            ->selectRaw('employee_id, SUM(advance_deduction) as total')
            ->pluck('total', 'employee_id');

        return $employeeIds->mapWithKeys(fn (int $id) => [
            $id => round((float) ($given[$id] ?? 0) - (float) ($recovered[$id] ?? 0), 2),
        ]);
    }

    /**
     * Creates the month's salary sheet for active staff who do not have a slip
     * yet. Outstanding advances are deducted by default, and staff who joined
     * during the month are marked absent for the days before they joined.
     *
     * @return Collection<int, SalarySlip> the slips created
     *
     * @throws ValidationException
     */
    public function prepare(DateTimeInterface|string $month): Collection
    {
        $month = Carbon::parse($month)->startOfMonth();

        if ($month->gt(BusinessClock::today()->startOfMonth())) {
            throw ValidationException::withMessages(['month' => 'Salaries can only be prepared up to the current month.']);
        }

        BusinessClock::assertTrustworthy();

        return DB::transaction(function () use ($month) {
            $employees = Employee::query()
                ->active()
                ->whereDate('joined_on', '<=', $month->copy()->endOfMonth())
                ->whereDoesntHave('salarySlips', fn ($query) => $query->whereDate('month', $month))
                ->orderBy('name')
                ->get();

            $outstanding = $this->outstandingAdvances($employees->pluck('id'));
            $pending = $this->pendingAdvanceDeductions($employees->pluck('id'));

            return $employees->map(function (Employee $employee) use ($month, $outstanding, $pending) {
                $days = $month->daysInMonth;
                $absent = $employee->joined_on->gt($month) ? (float) ($employee->joined_on->day - 1) : 0.0;
                $basic = (float) $employee->monthly_salary;

                $earned = $basic - $this->absenceDeduction($basic, $days, $absent);
                $available = max(0, ($outstanding[$employee->id] ?? 0) - ($pending[$employee->id] ?? 0));

                return SalarySlip::create([
                    'employee_id' => $employee->id,
                    'month' => $month->toDateString(),
                    'days_in_month' => $days,
                    'status' => SalarySlipStatus::Pending,
                    ...$this->figures($basic, $days, $absent, 0, 0, min($available, max(0, $earned))),
                ]);
            });
        });
    }

    /**
     * @throws ValidationException
     */
    public function update(
        SalarySlip $slip,
        float $absentDays,
        float $bonus,
        float $otherDeduction,
        float $advanceDeduction,
        ?string $notes = null,
    ): SalarySlip {
        return DB::transaction(function () use ($slip, $absentDays, $bonus, $otherDeduction, $advanceDeduction, $notes) {
            $slip = $this->lockPending($slip);

            if ($absentDays > $slip->days_in_month) {
                throw ValidationException::withMessages(['absent_days' => "Absent days cannot be more than {$slip->days_in_month}."]);
            }

            $available = $this->availableAdvance($slip);

            if ($advanceDeduction > $available + 0.005) {
                throw ValidationException::withMessages([
                    'advance_deduction' => 'Only Rs. '.number_format($available, 2).' of advances is left to recover.',
                ]);
            }

            $figures = $this->figures((float) $slip->basic_salary, $slip->days_in_month, $absentDays, $bonus, $otherDeduction, $advanceDeduction);

            if ($figures['net_salary'] < 0) {
                throw ValidationException::withMessages(['other_deduction' => 'Deductions cannot be more than the salary earned.']);
            }

            $slip->update([...$figures, 'notes' => $notes]);

            return $slip;
        });
    }

    /**
     * Pays a slip on the business day (today unless given). The net amount is
     * written as a salary expense of that day.
     *
     * @throws ValidationException
     */
    public function pay(
        SalarySlip $slip,
        PaymentMethod $paymentMethod = PaymentMethod::Cash,
        ?User $paidBy = null,
        DateTimeInterface|string|null $date = null,
    ): SalarySlip {
        return DB::transaction(function () use ($slip, $paymentMethod, $paidBy, $date) {
            $slip = $this->lockPending($slip);
            $today = $date ? Carbon::parse($date)->startOfDay() : BusinessClock::today();

            $this->closings->assertOpen($today);

            if ((float) $slip->advance_deduction > $this->availableAdvance($slip) + 0.005) {
                throw ValidationException::withMessages([
                    'record' => "The advance deduction for {$slip->employee->name} is more than what is left to recover. Edit the slip first.",
                ]);
            }

            $expense = (float) $slip->net_salary > 0
                ? $this->expenses->record(
                    date: $today,
                    category: ExpenseCategory::Salary,
                    description: sprintf('Salary %s: %s', $slip->month->format('F Y'), $slip->employee->name),
                    amount: (float) $slip->net_salary,
                    paymentMethod: $paymentMethod,
                    recordedBy: $paidBy,
                )
                : null;

            $slip->update([
                'status' => SalarySlipStatus::Paid,
                'paid_on' => $today->toDateString(),
                'payment_method' => $paymentMethod,
                'expense_id' => $expense?->id,
                'paid_by' => $paidBy?->id,
            ]);

            return $slip;
        });
    }

    /**
     * Pays every pending slip of the month in one go.
     *
     * @return int number of slips paid
     *
     * @throws ValidationException
     */
    public function payAll(DateTimeInterface|string $month, PaymentMethod $paymentMethod = PaymentMethod::Cash, ?User $paidBy = null): int
    {
        $month = Carbon::parse($month)->startOfMonth();

        return DB::transaction(function () use ($month, $paymentMethod, $paidBy) {
            $slips = SalarySlip::query()
                ->whereDate('month', $month)
                ->where('status', SalarySlipStatus::Pending->value)
                ->get();

            $slips->each(fn (SalarySlip $slip) => $this->pay($slip, $paymentMethod, $paidBy));

            return $slips->count();
        });
    }

    /**
     * Undoes a payment while the day it was paid is still open.
     *
     * @throws ValidationException
     */
    public function reversePayment(SalarySlip $slip): SalarySlip
    {
        return DB::transaction(function () use ($slip) {
            $slip = SalarySlip::query()->with('expense')->lockForUpdate()->findOrFail($slip->id);

            if (! $slip->isPaid()) {
                throw ValidationException::withMessages(['record' => 'This salary has not been paid yet.']);
            }

            $this->closings->assertOpen($slip->paid_on);

            $expense = $slip->expense;

            $slip->update([
                'status' => SalarySlipStatus::Pending,
                'paid_on' => null,
                'payment_method' => null,
                'expense_id' => null,
                'paid_by' => null,
            ]);

            $expense?->delete();

            return $slip;
        });
    }

    /**
     * Removes an unpaid slip, e.g. for someone who left before payday.
     *
     * @throws ValidationException
     */
    public function delete(SalarySlip $slip): void
    {
        DB::transaction(fn () => $this->lockPending($slip)->delete());
    }

    /**
     * An advance given after the month's sheet was prepared is added to that
     * unpaid slip straight away, as far as the salary allows.
     */
    private function addToPendingSlip(Employee $employee, float $amount): void
    {
        $slip = SalarySlip::query()
            ->where('employee_id', $employee->id)
            ->where('status', SalarySlipStatus::Pending->value)
            ->orderByDesc('month')
            ->lockForUpdate()
            ->first();

        $add = $slip ? min($amount, max(0.0, (float) $slip->net_salary)) : 0.0;

        if ($add <= 0) {
            return;
        }

        $slip->update([
            'advance_deduction' => round((float) $slip->advance_deduction + $add, 2),
            'net_salary' => round((float) $slip->net_salary - $add, 2),
        ]);
    }

    /**
     * After an advance is deleted, unpaid slips may not recover more than
     * is still owed. Oldest slips keep their deduction first.
     */
    private function trimPendingSlips(Employee $employee): void
    {
        $owed = $this->outstandingAdvance($employee);

        SalarySlip::query()
            ->where('employee_id', $employee->id)
            ->where('status', SalarySlipStatus::Pending->value)
            ->orderBy('month')
            ->lockForUpdate()
            ->get()
            ->each(function (SalarySlip $slip) use (&$owed) {
                $keep = round(min((float) $slip->advance_deduction, max(0.0, $owed)), 2);
                $owed -= $keep;
                $released = round((float) $slip->advance_deduction - $keep, 2);

                if ($released > 0) {
                    $slip->update([
                        'advance_deduction' => $keep,
                        'net_salary' => round((float) $slip->net_salary + $released, 2),
                    ]);
                }
            });
    }

    /**
     * @return array{basic_salary: float, absent_days: float, absence_deduction: float, bonus: float, other_deduction: float, advance_deduction: float, net_salary: float}
     */
    private function figures(float $basic, int $days, float $absentDays, float $bonus, float $otherDeduction, float $advanceDeduction): array
    {
        $absence = $this->absenceDeduction($basic, $days, $absentDays);

        return [
            'basic_salary' => round($basic, 2),
            'absent_days' => round($absentDays, 1),
            'absence_deduction' => $absence,
            'bonus' => round($bonus, 2),
            'other_deduction' => round($otherDeduction, 2),
            'advance_deduction' => round($advanceDeduction, 2),
            'net_salary' => round($basic - $absence + $bonus - $otherDeduction - $advanceDeduction, 2),
        ];
    }

    /**
     * One day's pay is the monthly salary divided by the days in that month,
     * rounded to whole rupees.
     */
    private function absenceDeduction(float $basic, int $days, float $absentDays): float
    {
        return $absentDays > 0 ? (float) round($basic / $days * $absentDays) : 0.0;
    }

    /**
     * Advance still free to recover on this slip: outstanding advances minus
     * what the employee's other unpaid slips already plan to deduct.
     */
    private function availableAdvance(SalarySlip $slip): float
    {
        $ids = collect([$slip->employee_id]);

        $otherPending = (float) SalarySlip::query()
            ->where('employee_id', $slip->employee_id)
            ->where('status', SalarySlipStatus::Pending->value)
            ->whereKeyNot($slip->id)
            ->sum('advance_deduction');

        return max(0.0, round(($this->outstandingAdvances($ids)->get($slip->employee_id) ?? 0) - $otherPending, 2));
    }

    /**
     * @param  Collection<int, int>  $employeeIds
     * @return Collection<int, float>
     */
    private function pendingAdvanceDeductions(Collection $employeeIds): Collection
    {
        return SalarySlip::query()
            ->whereIn('employee_id', $employeeIds)
            ->where('status', SalarySlipStatus::Pending->value)
            ->groupBy('employee_id')
            ->selectRaw('employee_id, SUM(advance_deduction) as total')
            ->pluck('total', 'employee_id')
            ->map(fn ($total) => (float) $total);
    }

    /**
     * @throws ValidationException
     */
    private function lockPending(SalarySlip $slip): SalarySlip
    {
        $slip = SalarySlip::query()->with('employee')->lockForUpdate()->findOrFail($slip->id);

        if ($slip->isPaid()) {
            throw ValidationException::withMessages([
                'record' => "{$slip->employee->name}'s salary for {$slip->month->format('F Y')} is already paid. Reverse the payment first.",
            ]);
        }

        return $slip;
    }
}
