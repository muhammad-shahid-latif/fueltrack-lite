<?php

namespace App\Services;

use App\Models\FuelProduct;
use App\Models\Sale;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleService
{
    public function __construct(
        private DailyClosingService $closings,
        private FuelStockService $stock,
    ) {}

    /**
     * Records a counter sale of a non-metered item such as engine oil.
     * Fuel is always sold through meter readings instead.
     *
     * @throws ValidationException
     */
    public function recordCounterSale(
        FuelProduct $product,
        DateTimeInterface|string $date,
        float $quantity,
        ?User $recordedBy = null,
        ?float $rate = null,
        ?string $notes = null,
    ): Sale {
        $date = Carbon::parse($date)->toDateString();

        return DB::transaction(function () use ($product, $date, $quantity, $recordedBy, $rate, $notes) {
            $this->closings->assertOpen($date);

            if ($product->isMetered()) {
                throw ValidationException::withMessages([
                    'fuel_product_id' => 'Fuel is sold through meter readings, not counter sales.',
                ]);
            }

            if (! $product->is_active) {
                throw ValidationException::withMessages(['fuel_product_id' => "{$product->name} is inactive."]);
            }

            $available = $this->stock->currentStock($product, $date);

            if ($quantity > $available + 0.005) {
                throw ValidationException::withMessages([
                    'quantity' => sprintf('Only %s %s of %s in stock.', rtrim(rtrim(number_format($available, 2), '0'), '.'), $product->unit->value, $product->name),
                ]);
            }

            $rate = round($rate ?? (float) $product->selling_price, 2);

            return Sale::create([
                'fuel_product_id' => $product->id,
                'business_date' => $date,
                'quantity' => round($quantity, 2),
                'rate' => $rate,
                'amount' => round($quantity * $rate, 2),
                'recorded_by' => $recordedBy?->id,
                'notes' => $notes,
            ]);
        });
    }
}
