<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Sales come from nozzle meter readings (fuel) or the counter (oil and other
 * items). Created only through MeterReadingService or SaleService.
 */
class Sale extends Model
{
    use LogsActivity;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'meter_reading_id',
        'fuel_product_id',
        'tank_id',
        'customer_id',
        'business_date',
        'quantity',
        'rate',
        'amount',
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
            'quantity' => 'decimal:2',
            'rate' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<MeterReading, $this>
     */
    public function meterReading(): BelongsTo
    {
        return $this->belongsTo(MeterReading::class);
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
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @return BelongsTo<Tank, $this>
     */
    public function tank(): BelongsTo
    {
        return $this->belongsTo(Tank::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasOne<CustomerCredit, $this>
     */
    public function credit(): HasOne
    {
        return $this->hasOne(CustomerCredit::class);
    }

    public function isOnCredit(): bool
    {
        return $this->customer_id !== null;
    }

    public function isCounterSale(): bool
    {
        return $this->meter_reading_id === null;
    }

    public function activityLabel(): string
    {
        return 'Sale of Rs. '.number_format((float) $this->amount, 2);
    }
}
