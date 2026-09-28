<?php

namespace App\Models;

use Database\Factories\NozzleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Nozzle extends Model
{
    /** @use HasFactory<NozzleFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'pump_id',
        'fuel_product_id',
        'number',
        'current_meter',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'current_meter' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Pump, $this>
     */
    public function pump(): BelongsTo
    {
        return $this->belongsTo(Pump::class);
    }

    /**
     * @return BelongsTo<FuelProduct, $this>
     */
    public function fuelProduct(): BelongsTo
    {
        return $this->belongsTo(FuelProduct::class);
    }

    /**
     * @return HasMany<MeterReading, $this>
     */
    public function meterReadings(): HasMany
    {
        return $this->hasMany(MeterReading::class);
    }

    /**
     * Human label, e.g. "Pump 01 · Nozzle 2". Requires the pump relation.
     */
    public function label(): string
    {
        return "{$this->pump->name} · Nozzle {$this->number}";
    }
}
