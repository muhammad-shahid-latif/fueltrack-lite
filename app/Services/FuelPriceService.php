<?php

namespace App\Services;

use App\Models\FuelPrice;
use App\Models\FuelProduct;
use App\Models\User;
use App\Support\BusinessClock;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FuelPriceService
{
    /**
     * Changes a selling price. The new price takes effect at the server's
     * current time and the change is kept in the price history.
     *
     * @throws ValidationException
     */
    public function change(FuelProduct $product, float $price, ?User $changedBy = null, ?string $note = null): FuelPrice
    {
        BusinessClock::assertTrustworthy();

        $price = round($price, 2);

        if (abs($price - (float) $product->selling_price) < 0.005 && $product->prices()->exists()) {
            throw ValidationException::withMessages([
                'price' => "{$product->name} is already priced at Rs. ".number_format($price, 2).'.',
            ]);
        }

        return $this->record($product, $price, BusinessClock::now(), $changedBy, $note);
    }

    /**
     * Writes a history entry and updates the product's current price.
     * Also used by seeders to build past price history.
     */
    public function record(
        FuelProduct $product,
        float $price,
        DateTimeInterface $effectiveAt,
        ?User $changedBy = null,
        ?string $note = null,
    ): FuelPrice {
        return DB::transaction(function () use ($product, $price, $effectiveAt, $changedBy, $note) {
            $previous = $product->prices()->first();

            $entry = $product->prices()->create([
                'price' => round($price, 2),
                'previous_price' => $previous?->price,
                'effective_at' => $effectiveAt,
                'changed_by' => $changedBy?->id,
                'note' => $note,
            ]);

            $product->update(['selling_price' => round($price, 2)]);

            return $entry;
        });
    }
}
