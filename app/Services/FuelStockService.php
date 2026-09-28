<?php

namespace App\Services;

use App\Models\FuelProduct;
use App\Models\FuelPurchase;
use App\Models\Sale;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Stock is derived from a ledger, never stored:
 * opening stock + purchases − sales = closing stock.
 */
class FuelStockService
{
    /**
     * @return Collection<int, array{product: FuelProduct, opening: float, received: float, sold: float, closing: float, is_low: bool}>
     */
    public function summary(DateTimeInterface|string $date): Collection
    {
        $date = Carbon::parse($date)->toDateString();

        $received = $this->totalsByProduct(FuelPurchase::query(), 'quantity', $date);
        $sold = $this->totalsByProduct(Sale::query(), 'liters', $date);

        return FuelProduct::query()
            ->active()
            ->orderBy('id')
            ->get()
            ->map(function (FuelProduct $product) use ($received, $sold) {
                $in = $received->get($product->id);
                $out = $sold->get($product->id);

                $opening = round((float) $product->opening_stock + (float) ($in->before_date ?? 0) - (float) ($out->before_date ?? 0), 2);
                $receivedToday = round((float) ($in->on_date ?? 0), 2);
                $soldToday = round((float) ($out->on_date ?? 0), 2);
                $closing = round($opening + $receivedToday - $soldToday, 2);

                return [
                    'product' => $product,
                    'opening' => $opening,
                    'received' => $receivedToday,
                    'sold' => $soldToday,
                    'closing' => $closing,
                    'is_low' => $closing <= (float) $product->low_stock_threshold,
                ];
            });
    }

    public function currentStock(FuelProduct $product, DateTimeInterface|string $asOf): float
    {
        return (float) $this->summary($asOf)
            ->firstWhere('product.id', $product->id)['closing'];
    }

    /**
     * One grouped query per ledger: totals before the date and on the date.
     *
     * @param  Builder<FuelPurchase>|Builder<Sale>  $query
     * @return Collection<int, object{before_date: string, on_date: string}>
     */
    private function totalsByProduct($query, string $column, string $date): Collection
    {
        return $query
            ->whereDate('business_date', '<=', $date)
            ->groupBy('fuel_product_id')
            ->select('fuel_product_id')
            ->selectRaw("COALESCE(SUM(CASE WHEN DATE(business_date) < ? THEN {$column} ELSE 0 END), 0) as before_date", [$date])
            ->selectRaw("COALESCE(SUM(CASE WHEN DATE(business_date) = ? THEN {$column} ELSE 0 END), 0) as on_date", [$date])
            ->toBase()
            ->get()
            ->keyBy('fuel_product_id');
    }
}
