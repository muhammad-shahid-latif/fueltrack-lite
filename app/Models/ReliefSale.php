<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A day's PM Fuel Relief total for one fuel, as shown in the government
 * system: tokens redeemed and the relief amount (relief_rate holds the
 * amount per token). quantity is the liters that amount buys at the pump
 * price. The relief is claimed from the government. Created only through
 * ReliefService.
 */
class ReliefSale extends Model
{
    use LogsActivity;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'business_date',
        'fuel_product_id',
        'vehicle_type',
        'tokens',
        'quantity',
        'quota_liters',
        'price',
        'relief_rate',
        'relief_amount',
        'token_code',
        'token_expires_on',
        'vehicle_number',
        'owner_verified',
        'received_on',
        'receipt_reference',
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
            'tokens' => 'integer',
            'quantity' => 'decimal:2',
            'quota_liters' => 'decimal:2',
            'price' => 'decimal:2',
            'relief_rate' => 'decimal:2',
            'relief_amount' => 'decimal:2',
            'received_on' => 'date',
            'token_expires_on' => 'date',
            'owner_verified' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<FuelProduct, $this>
     */
    public function fuelProduct(): BelongsTo
    {
        return $this->belongsTo(FuelProduct::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function isReceived(): bool
    {
        return $this->received_on !== null;
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->whereNull('received_on');
    }

    public function activityLabel(): string
    {
        return sprintf('Fuel relief %s: %s L', $this->business_date?->format('d M Y'), number_format((float) $this->quantity, 2));
    }
}
