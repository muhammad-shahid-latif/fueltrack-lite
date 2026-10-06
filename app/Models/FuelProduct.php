<?php

namespace App\Models;

use App\Enums\ProductType;
use App\Enums\ProductUnit;
use Database\Factories\FuelProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A sellable product. Despite the historical table name this covers fuel
 * (sold through nozzle meters) and counter items such as engine oil.
 */
class FuelProduct extends Model
{
    /** @use HasFactory<FuelProductFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'type',
        'unit',
        'selling_price',
        'opening_stock',
        'low_stock_threshold',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'unit' => ProductUnit::class,
            'selling_price' => 'decimal:2',
            'opening_stock' => 'decimal:2',
            'low_stock_threshold' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Nozzle, $this>
     */
    public function nozzles(): HasMany
    {
        return $this->hasMany(Nozzle::class);
    }

    /**
     * @return HasMany<Sale, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /**
     * @return HasMany<FuelPurchase, $this>
     */
    public function purchases(): HasMany
    {
        return $this->hasMany(FuelPurchase::class);
    }

    /**
     * @return HasMany<FuelPrice, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(FuelPrice::class)->latest('effective_at')->latest('id');
    }

    /**
     * @return HasOne<FuelPrice, $this>
     */
    public function latestPrice(): HasOne
    {
        return $this->hasOne(FuelPrice::class)->latestOfMany('effective_at');
    }

    public function isMetered(): bool
    {
        return $this->type === ProductType::Fuel;
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeFuel(Builder $query): void
    {
        $query->where('type', ProductType::Fuel->value);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeCounterItems(Builder $query): void
    {
        $query->where('type', '!=', ProductType::Fuel->value);
    }
}
