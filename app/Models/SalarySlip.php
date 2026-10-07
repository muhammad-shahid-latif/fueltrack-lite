<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\SalarySlipStatus;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Salary slips are only created and changed through PayrollService.
 */
class SalarySlip extends Model
{
    use LogsActivity;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'month',
        'basic_salary',
        'days_in_month',
        'absent_days',
        'absence_deduction',
        'bonus',
        'other_deduction',
        'advance_deduction',
        'net_salary',
        'status',
        'paid_on',
        'payment_method',
        'expense_id',
        'paid_by',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'month' => 'date',
            'basic_salary' => 'decimal:2',
            'days_in_month' => 'integer',
            'absent_days' => 'decimal:1',
            'absence_deduction' => 'decimal:2',
            'bonus' => 'decimal:2',
            'other_deduction' => 'decimal:2',
            'advance_deduction' => 'decimal:2',
            'net_salary' => 'decimal:2',
            'status' => SalarySlipStatus::class,
            'paid_on' => 'date',
            'payment_method' => PaymentMethod::class,
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<Expense, $this>
     */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function isPaid(): bool
    {
        return $this->status === SalarySlipStatus::Paid;
    }

    /**
     * Earned before any deduction for advances or other recoveries.
     */
    public function grossSalary(): float
    {
        return round((float) $this->basic_salary - (float) $this->absence_deduction + (float) $this->bonus, 2);
    }

    public function totalDeductions(): float
    {
        return round((float) $this->absence_deduction + (float) $this->other_deduction + (float) $this->advance_deduction, 2);
    }

    public function activityLabel(): string
    {
        return 'Salary slip '.$this->month?->format('M Y').' for employee #'.$this->employee_id.' (Rs. '.number_format((float) $this->net_salary, 2).')';
    }
}
