<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One credit sale in the Khata register. Created only through CreditService.
 */
class CustomerCredit extends Model
{
    use LogsActivity;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'sale_id',
        'fuel_product_id',
        'business_date',
        'quantity',
        'owed_quantity',
        'rate',
        'amount',
        'vehicle_number',
        'slip_number',
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
            'quantity' => 'decimal:2',
            'owed_quantity' => 'decimal:2',
            'rate' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
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

    /**
     * Later deliveries of the owed part of this slip.
     *
     * @return HasMany<OwedFuelDelivery, $this>
     */
    public function owedDeliveries(): HasMany
    {
        return $this->hasMany(OwedFuelDelivery::class);
    }

    /** Liters billed on this slip and given at the time. */
    public function givenQuantity(): float
    {
        return round((float) $this->quantity - (float) $this->owed_quantity, 2);
    }

    /**
     * Liters still to give. Uses the loaded deliveries (or their
     * owed_delivered_sum aggregate) when available.
     */
    public function owedRemaining(): float
    {
        $delivered = match (true) {
            array_key_exists('owed_delivered_sum', $this->attributes) => $this->attributes['owed_delivered_sum'],
            $this->relationLoaded('owedDeliveries') => $this->owedDeliveries->sum('quantity'),
            default => $this->owedDeliveries()->sum('quantity'),
        };

        return round(max(0, (float) $this->owed_quantity - (float) $delivered), 2);
    }

    /** Value of the owed part at the slip's own rate. */
    public function owedValue(): float
    {
        return round((float) $this->owed_quantity * (float) $this->rate, 2);
    }

    public function isCounterSale(): bool
    {
        return $this->sale_id !== null;
    }

    public function activityLabel(): string
    {
        return 'Credit sale of Rs. '.number_format((float) $this->amount, 2);
    }
}
