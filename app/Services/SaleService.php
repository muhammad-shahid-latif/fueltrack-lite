<?php

namespace App\Services;

use App\Models\Customer;
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
        private CreditService $credits,
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
        ?Customer $customer = null,
        ?string $vehicleNumber = null,
    ): Sale {
        $date = Carbon::parse($date)->toDateString();

        return DB::transaction(function () use ($product, $date, $quantity, $recordedBy, $rate, $notes, $customer, $vehicleNumber) {
            $this->closings->assertOpen($date);

            if ($product->isMetered()) {
                throw ValidationException::withMessages([
                    'fuel_product_id' => 'Fuel is sold through meter readings, not counter sales.',
                ]);
            }

            if (! $product->is_active) {
                throw ValidationException::withMessages(['fuel_product_id' => "{$product->name} is inactive."]);
            }

            $this->assertInStock($product, $quantity, $this->stock->currentStock($product, $date));

            $rate = round($rate ?? (float) $product->selling_price, 2);

            $sale = Sale::create([
                'fuel_product_id' => $product->id,
                'customer_id' => $customer?->id,
                'business_date' => $date,
                'quantity' => round($quantity, 2),
                'rate' => $rate,
                'amount' => round($quantity * $rate, 2),
                'recorded_by' => $recordedBy?->id,
                'notes' => $notes,
            ]);

            if ($customer) {
                $this->credits->attachSale($sale, $customer, $vehicleNumber, $recordedBy);
            }

            return $sale;
        });
    }

    /**
     * Corrects a counter sale. Stock follows automatically because it is
     * calculated from the sales ledger.
     *
     * @throws ValidationException
     */
    public function updateCounterSale(Sale $sale, float $quantity, float $rate, ?string $notes = null): Sale
    {
        return DB::transaction(function () use ($sale, $quantity, $rate, $notes) {
            $sale = $this->lockCounterSale($sale);

            // The quantity already on this sale is available again for the corrected one.
            $available = $this->stock->currentStock($sale->fuelProduct, $sale->business_date) + (float) $sale->quantity;
            $this->assertInStock($sale->fuelProduct, $quantity, $available);

            $sale->update([
                'quantity' => round($quantity, 2),
                'rate' => round($rate, 2),
                'amount' => round($quantity * $rate, 2),
                'notes' => $notes,
            ]);

            $this->credits->syncSale($sale);

            return $sale;
        });
    }

    /**
     * Deletes a counter sale and returns its quantity to stock.
     *
     * @throws ValidationException
     */
    public function deleteCounterSale(Sale $sale): void
    {
        DB::transaction(function () use ($sale) {
            $sale = $this->lockCounterSale($sale);

            // Remove the linked debt first so the audit trail shows both.
            $sale->credit()->first()?->delete();
            $sale->delete();
        });
    }

    /**
     * @throws ValidationException
     */
    private function lockCounterSale(Sale $sale): Sale
    {
        $sale = Sale::query()->with('fuelProduct')->lockForUpdate()->findOrFail($sale->id);

        if (! $sale->isCounterSale()) {
            throw ValidationException::withMessages([
                'record' => 'Fuel sales come from meter readings. Edit the reading instead.',
            ]);
        }

        $this->closings->assertOpen($sale->business_date);

        return $sale;
    }

    /**
     * @throws ValidationException
     */
    private function assertInStock(FuelProduct $product, float $quantity, float $available): void
    {
        if ($quantity > $available + 0.005) {
            throw ValidationException::withMessages([
                'quantity' => sprintf('Only %s %s of %s in stock.', rtrim(rtrim(number_format($available, 2), '0'), '.'), $product->unit->value, $product->name),
            ]);
        }
    }
}
