<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Database\Factories\FuelPurchaseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuelPurchase extends Model
{
    /** @use HasFactory<FuelPurchaseFactory> */
    use HasFactory, LogsActivity;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'fuel_product_id',
        'tank_id',
        'supplier_id',
        'business_date',
        'due_date',
        'quantity',
        'rate',
        'amount',
        'average_cost',
        'supplier_name',
        'invoice_number',
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
            'due_date' => 'date',
            'quantity' => 'decimal:2',
            'rate' => 'decimal:2',
            'amount' => 'decimal:2',
            'average_cost' => 'decimal:4',
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
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Supplier name from the register, or the free text of older entries.
     */
    public function supplierLabel(): ?string
    {
        return $this->supplier_id ? $this->supplier?->name : $this->supplier_name;
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
        return 'Purchase of '.number_format((float) $this->quantity, 2).' for Rs. '.number_format((float) $this->amount, 2);
    }
}
