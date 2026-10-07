<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One credit sale in the debt register. Created only through CreditService.
 */
class CustomerCredit extends Model
{
    use LogsActivity;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'sale_id',
        'fuel_product_id',
        'business_date',
        'quantity',
        'rate',
        'amount',
        'vehicle_number',
        'slip_number',
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
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
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

    public function isCounterSale(): bool
    {
        return $this->sale_id !== null;
    }

    public function activityLabel(): string
    {
        return 'Credit sale of Rs. '.number_format((float) $this->amount, 2);
    }
}
