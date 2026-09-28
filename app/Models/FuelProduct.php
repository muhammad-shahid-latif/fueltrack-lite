<?php

namespace App\Models;

use Database\Factories\FuelProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
