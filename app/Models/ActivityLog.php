<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One audit-trail entry. Written by the LogsActivity trait, never edited.
 */
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    /** Module name shown for each audited model. */
    public const MODULES = [
        MeterReading::class => 'Meter readings',
        Sale::class => 'Sales',
        FuelPurchase::class => 'Purchases',
        FuelPrice::class => 'Prices',
        FuelProduct::class => 'Products',
        Expense::class => 'Office expense',
        DailyClosing::class => 'Daily closing',
        Tank::class => 'Tanks',
        StockAdjustment::class => 'Tank adjustments',
        Pump::class => 'Pumps',
        Nozzle::class => 'Nozzles',
        Supplier::class => 'Suppliers',
        SupplierPayment::class => 'Supplier payments',
        Customer::class => 'Customers',
        CustomerCredit::class => 'Credit sales',
        CustomerPayment::class => 'Customer payments',
        Employee::class => 'Employees',
        SalaryAdvance::class => 'Salary advances',
        SalarySlip::class => 'Salary slips',
    ];

    /** Models that make up the payroll audit trail. */
    public const PAYROLL = [Employee::class, SalaryAdvance::class, SalarySlip::class];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'event',
        'subject_type',
        'subject_id',
        'description',
        'changes',
        'ip_address',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function module(): string
    {
        return self::MODULES[$this->subject_type] ?? class_basename($this->subject_type);
    }

    public function eventBadge(): string
    {
        return match ($this->event) {
            'created' => 'text-bg-success',
            'updated' => 'text-bg-warning',
            'deleted' => 'text-bg-danger',
            default => 'text-bg-secondary',
        };
    }

    /**
     * Field changes as rows for display.
     *
     * @return list<array{field: string, old: mixed, new: mixed}>
     */
    public function changeRows(): array
    {
        $rows = [];

        foreach ($this->changes ?? [] as $field => $value) {
            if ($this->event === 'updated' && is_array($value) && array_key_exists('new', $value)) {
                $rows[] = ['field' => $field, 'old' => $value['old'], 'new' => $value['new']];
            } elseif ($this->event === 'deleted') {
                $rows[] = ['field' => $field, 'old' => $value, 'new' => null];
            } else {
                $rows[] = ['field' => $field, 'old' => null, 'new' => $value];
            }
        }

        return $rows;
    }

    /**
     * @param  Builder<self>  $query
     * @param  list<class-string>  $types
     */
    public function scopeForTypes(Builder $query, array $types): void
    {
        $query->whereIn('subject_type', $types);
    }
}
