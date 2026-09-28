<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Meter readings are only created through MeterReadingService.
 */
class MeterReading extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'nozzle_id',
        'business_date',
        'opening_meter',
        'closing_meter',
        'liters',
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
            'opening_meter' => 'decimal:2',
            'closing_meter' => 'decimal:2',
            'liters' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Nozzle, $this>
     */
    public function nozzle(): BelongsTo
    {
        return $this->belongsTo(Nozzle::class);
    }

    /**
     * @return HasOne<Sale, $this>
     */
    public function sale(): HasOne
    {
        return $this->hasOne(Sale::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
