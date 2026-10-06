<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sales come from nozzle meter readings (fuel) or the counter (oil and other
 * items). Created only through MeterReadingService or SaleService.
 */
class Sale extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'meter_reading_id',
        'fuel_product_id',
        'business_date',
        'quantity',
        'rate',
        'amount',
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
            'rate' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<MeterReading, $this>
     */
    public function meterReading(): BelongsTo
    {
        return $this->belongsTo(MeterReading::class);
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

    public function isCounterSale(): bool
    {
        return $this->meter_reading_id === null;
    }
}
