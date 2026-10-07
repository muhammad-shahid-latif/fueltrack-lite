<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Database\Factories\TankFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An underground storage tank holding one fuel. Its level is never stored;
 * TankService works it out from deliveries, nozzle sales and adjustments.
 */
class Tank extends Model
{
    /** @use HasFactory<TankFactory> */
    use HasFactory, LogsActivity;

    /** Below this share of capacity a tank is flagged low, then critical. */
    public const LOW_RATIO = 0.25;

    public const CRITICAL_RATIO = 0.10;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'fuel_product_id',
        'capacity',
        'opening_stock',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capacity' => 'decimal:2',
            'opening_stock' => 'decimal:2',
            'is_active' => 'boolean',
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
     * @return HasMany<Nozzle, $this>
     */
    public function nozzles(): HasMany
    {
        return $this->hasMany(Nozzle::class);
    }

    /**
     * @return HasMany<FuelPurchase, $this>
     */
    public function purchases(): HasMany
    {
        return $this->hasMany(FuelPurchase::class);
    }

    /**
     * @return HasMany<Sale, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /**
     * @return HasMany<StockAdjustment, $this>
     */
    public function adjustments(): HasMany
    {
        return $this->hasMany(StockAdjustment::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function hasHistory(): bool
    {
        return $this->purchases()->exists() || $this->sales()->exists() || $this->adjustments()->exists();
    }

    public function activityLabel(): string
    {
        return "Tank {$this->name}";
    }
}
