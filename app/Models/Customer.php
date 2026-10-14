<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A credit customer: fleet owner, transporter or regular account holder.
 */
class Customer extends Model
{
    use LogsActivity;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'phone',
        'vehicle_number',
        'address',
        'credit_limit',
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
            'credit_limit' => 'decimal:2',
            'opening_balance' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<OwedFuelDelivery, $this>
     */
    public function owedDeliveries(): HasMany
    {
        return $this->hasMany(OwedFuelDelivery::class);
    }

    /**
     * @return HasMany<CustomerCredit, $this>
     */
    public function credits(): HasMany
    {
        return $this->hasMany(CustomerCredit::class);
    }

    /**
     * @return HasMany<CustomerPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function initials(): string
    {
        return collect(preg_split('/\s+/', trim($this->name)))
            ->filter()
            ->take(2)
            ->map(fn (string $word) => mb_strtoupper(mb_substr($word, 0, 1)))
            ->join('');
    }

    public function activityLabel(): string
    {
        return "Customer {$this->name}";
    }
}
