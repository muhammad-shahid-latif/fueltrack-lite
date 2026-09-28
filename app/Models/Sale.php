<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sales are only created through MeterReadingService, never directly.
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
        'liters',
        'rate',
        'amount',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'liters' => 'decimal:2',
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
}
