<?php

namespace App\Models;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Models\Concerns\LogsActivity;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use HasFactory, LogsActivity;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'business_date',
        'category',
        'description',
        'amount',
        'payment_method',
        'recorded_by',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'category' => ExpenseCategory::class,
            'payment_method' => PaymentMethod::class,
            'amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @return HasOne<SalarySlip, $this>
     */
    public function salarySlip(): HasOne
    {
        return $this->hasOne(SalarySlip::class);
    }

    /**
     * @return HasOne<SalaryAdvance, $this>
     */
    public function salaryAdvance(): HasOne
    {
        return $this->hasOne(SalaryAdvance::class);
    }

    /**
     * Salary and advance expenses belong to payroll and change only there.
     */
    public function isPayroll(): bool
    {
        return $this->category->isPayroll();
    }

    public function activityLabel(): string
    {
        return 'Expense '.$this->description.' Rs. '.number_format((float) $this->amount, 2);
    }
}
