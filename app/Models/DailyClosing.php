<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Daily closings are only created through DailyClosingService.
 */
class DailyClosing extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'business_date',
        'total_liters',
        'total_sales',
        'card_sales',
        'credit_sales',
        'cash_sales',
        'total_expenses',
        'cash_expenses',
        'opening_cash',
        'expected_cash',
        'actual_cash',
        'difference',
        'notes',
        'closed_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'total_liters' => 'decimal:2',
            'total_sales' => 'decimal:2',
            'card_sales' => 'decimal:2',
            'credit_sales' => 'decimal:2',
            'cash_sales' => 'decimal:2',
            'total_expenses' => 'decimal:2',
            'cash_expenses' => 'decimal:2',
            'opening_cash' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'actual_cash' => 'decimal:2',
            'difference' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function hasCashDifference(): bool
    {
        return (float) $this->difference !== 0.0;
    }
}
