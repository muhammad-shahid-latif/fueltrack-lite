<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use LogsActivity;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'contact_person',
        'phone',
        'address',
        'payment_terms_days',
        'opening_balance',
        'is_active',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payment_terms_days' => 'integer',
            'opening_balance' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<FuelPurchase, $this>
     */
    public function purchases(): HasMany
    {
        return $this->hasMany(FuelPurchase::class);
    }

    /**
     * @return HasMany<SupplierPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function termsLabel(): string
    {
        return $this->payment_terms_days > 0 ? "{$this->payment_terms_days} days credit" : 'Cash on delivery';
    }

    public function activityLabel(): string
    {
        return "Supplier {$this->name}";
    }
}
