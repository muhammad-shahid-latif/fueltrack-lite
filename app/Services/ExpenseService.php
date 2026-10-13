<?php

namespace App\Services;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Models\Expense;
use App\Models\User;
use App\Support\BusinessClock;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExpenseService
{
    public function __construct(private DailyClosingService $closings) {}

    /**
     * @throws ValidationException
     */
    public function record(
        DateTimeInterface|string $date,
        ExpenseCategory $category,
        string $description,
        float $amount,
        PaymentMethod $paymentMethod = PaymentMethod::Cash,
        ?User $recordedBy = null,
        ?string $notes = null,
        bool $allowClosedDay = false,
    ): Expense {
        $date = Carbon::parse($date)->toDateString();

        return DB::transaction(function () use ($date, $category, $description, $amount, $paymentMethod, $recordedBy, $notes, $allowClosedDay) {
            // Payroll may book bank payments on a closed day; they never touch the cash count.
            $allowClosedDay ? BusinessClock::assertTrustworthy() : $this->closings->assertOpen($date);

            return Expense::create([
                'business_date' => $date,
                'category' => $category,
                'description' => $description,
                'amount' => round($amount, 2),
                'payment_method' => $paymentMethod,
                'recorded_by' => $recordedBy?->id,
                'notes' => $notes,
            ]);
        });
    }

    /**
     * Owner correction of a register entry. Payroll entries change only in payroll.
     *
     * @throws ValidationException
     */
    public function update(
        Expense $expense,
        ExpenseCategory $category,
        string $description,
        float $amount,
        PaymentMethod $paymentMethod,
        ?string $notes = null,
    ): Expense {
        return DB::transaction(function () use ($expense, $category, $description, $amount, $paymentMethod, $notes) {
            $expense = $this->lockManual($expense);

            $expense->update([
                'category' => $category,
                'description' => $description,
                'amount' => round($amount, 2),
                'payment_method' => $paymentMethod,
                'notes' => $notes,
            ]);

            return $expense;
        });
    }

    /**
     * @throws ValidationException
     */
    public function delete(Expense $expense): void
    {
        DB::transaction(fn () => $this->lockManual($expense)->delete());
    }

    /**
     * @throws ValidationException
     */
    private function lockManual(Expense $expense): Expense
    {
        $expense = Expense::query()->lockForUpdate()->findOrFail($expense->id);

        if ($expense->isPayroll()) {
            throw ValidationException::withMessages(['record' => 'Salaries and advances are changed from Payroll.']);
        }

        $this->closings->assertOpen($expense->business_date, 'record');

        return $expense;
    }
}
