<?php

namespace App\Models;

use App\Enums\StockAdjustmentType;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A signed stock correction: a dip-reading variance or one leg of a
 * tank-to-tank transfer. Created only through TankService.
 */
class StockAdjustment extends Model
{
    use LogsActivity;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'fuel_product_id',
        'tank_id',
        'business_date',
        'type',
        'quantity',
        'dip_reading',
        'book_stock',
        'reference',
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
            'type' => StockAdjustmentType::class,
            'quantity' => 'decimal:2',
            'dip_reading' => 'decimal:2',
            'book_stock' => 'decimal:2',
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
     * @return BelongsTo<Tank, $this>
     */
    public function tank(): BelongsTo
    {
        return $this->belongsTo(Tank::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function activityLabel(): string
    {
        return sprintf('%s of %s L', $this->type?->label() ?? 'Adjustment', number_format((float) $this->quantity, 2));
    }
}
