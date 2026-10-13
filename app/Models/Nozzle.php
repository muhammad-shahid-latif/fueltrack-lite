<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Database\Factories\NozzleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Nozzle extends Model
{
    /** @use HasFactory<NozzleFactory> */
    use HasFactory, LogsActivity;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'pump_id',
        'fuel_product_id',
        'tank_id',
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
     * @return BelongsTo<Tank, $this>
     */
    public function tank(): BelongsTo
    {
        return $this->belongsTo(Tank::class);
    }

    /**
     * @return HasMany<MeterReading, $this>
     */
    public function meterReadings(): HasMany
    {
        return $this->hasMany(MeterReading::class);
    }

    /**
     * Machine order as people read it: "Machine 2" before "Machine 10",
     * then nozzle 1, 2, … Requires the pump relation.
     *
     * @param  Collection<int, self>  $nozzles
     * @return Collection<int, self>
     */
    public static function inDisplayOrder(Collection $nozzles): Collection
    {
        return $nozzles
            ->sort(fn (self $a, self $b) => Pump::compareNames($a->pump->name, $b->pump->name) ?: $a->number <=> $b->number)
            ->values();
    }

    /**
     * Human label, e.g. "Pump 01 · Nozzle 2". Requires the pump relation.
     */
    public function label(): string
    {
        return "{$this->pump->name} · Nozzle {$this->number}";
    }

    public function activityLabel(): string
    {
        return 'Nozzle #'.$this->number.' on pump #'.$this->pump_id;
    }
}
