<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Price history entries are only created through FuelPriceService.
 */
class FuelPrice extends Model
{
    use LogsActivity;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'fuel_product_id',
        'price',
        'previous_price',
        'effective_at',
        'changed_by',
        'note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'previous_price' => 'decimal:2',
            'effective_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function change(): ?float
    {
        return $this->previous_price === null ? null : round((float) $this->price - (float) $this->previous_price, 2);
    }
}
