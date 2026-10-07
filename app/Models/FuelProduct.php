<?php

namespace App\Models;

use App\Enums\ProductType;
use App\Enums\ProductUnit;
use App\Models\Concerns\LogsActivity;
use Database\Factories\FuelProductFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A sellable product. Despite the historical table name this covers fuel
 * (sold through nozzle meters) and counter items such as engine oil.
 */
class FuelProduct extends Model
{
    /** @use HasFactory<FuelProductFactory> */
    use HasFactory, LogsActivity;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'type',
        'unit',
        'selling_price',
        'cost_price',
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
            'cost_price' => 'decimal:2',
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
     * @return HasMany<Tank, $this>
     */
    public function tanks(): HasMany
    {
        return $this->hasMany(Tank::class);
    }

    /**
     * Moving weighted average cost per unit after the latest delivery,
     * falling back to the opening cost price.
     */
    public function currentAverageCost(DateTimeInterface|string|null $asOf = null): ?float
    {
        $latest = $this->purchases()
            ->whereNotNull('average_cost')
            ->when($asOf, fn (Builder $query) => $query->whereDate('business_date', '<=', Carbon::parse($asOf)))
            ->reorder()
            ->orderByDesc('business_date')
            ->orderByDesc('id')
            ->value('average_cost');

        if ($latest !== null) {
            return round((float) $latest, 4);
        }

        return $this->cost_price !== null ? (float) $this->cost_price : null;
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

    public function activityLabel(): string
    {
        return 'Product '.$this->name;
    }
}
