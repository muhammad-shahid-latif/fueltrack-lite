<?php

namespace App\Services;

use App\Models\FuelProduct;
use App\Models\FuelPurchase;
use App\Models\Sale;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Stock is derived from a ledger, never stored:
 * opening stock + purchases − sales = closing stock.
 */
class FuelStockService
{
    public function __construct(private DailyClosingService $closings) {}

    /**
     * Records stock received at the station (fuel tanker or counter items).
     *
     * @throws ValidationException
     */
    public function recordPurchase(
        FuelProduct $product,
        DateTimeInterface|string $date,
        float $quantity,
        float $rate,
        ?string $supplierName = null,
        ?string $invoiceNumber = null,
        ?User $recordedBy = null,
        ?string $notes = null,
    ): FuelPurchase {
        $date = Carbon::parse($date)->toDateString();

        return DB::transaction(function () use ($product, $date, $quantity, $rate, $supplierName, $invoiceNumber, $recordedBy, $notes) {
            $this->closings->assertOpen($date);

            return FuelPurchase::create([
                'fuel_product_id' => $product->id,
                'business_date' => $date,
                'quantity' => round($quantity, 2),
                'rate' => round($rate, 2),
                'amount' => round($quantity * $rate, 2),
                'supplier_name' => $supplierName,
                'invoice_number' => $invoiceNumber,
                'recorded_by' => $recordedBy?->id,
                'notes' => $notes,
            ]);
        });
    }

    /**
     * Stock movement for every active product, fuel first.
     *
     * @return Collection<int, array{product: FuelProduct, opening: float, received: float, sold: float, closing: float, is_low: bool}>
     */
    public function summary(DateTimeInterface|string $date): Collection
    {
        $products = FuelProduct::query()
            ->active()
            ->orderByRaw('CASE WHEN type = ? THEN 0 ELSE 1 END', ['fuel'])
            ->orderBy('id')
            ->get();

        return $this->movement($products, $date);
    }

    public function currentStock(FuelProduct $product, DateTimeInterface|string $asOf): float
    {
        return $this->movement(collect([$product]), $asOf)->first()['closing'];
    }

    /**
     * @param  Collection<int, FuelProduct>  $products
     * @return Collection<int, array{product: FuelProduct, opening: float, received: float, sold: float, closing: float, is_low: bool}>
     */
    private function movement(Collection $products, DateTimeInterface|string $date): Collection
    {
        $date = Carbon::parse($date)->toDateString();
        $ids = $products->pluck('id')->all();

        $received = $this->totalsByProduct(FuelPurchase::query(), 'quantity', $date, $ids);
        $sold = $this->totalsByProduct(Sale::query(), 'quantity', $date, $ids);

        return $products->map(function (FuelProduct $product) use ($received, $sold) {
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
        })->values();
    }

    /**
     * One grouped query per ledger: totals before the date and on the date.
     *
     * @param  Builder<FuelPurchase>|Builder<Sale>  $query
     * @param  list<int>  $productIds
     * @return Collection<int, object{before_date: string, on_date: string}>
     */
    private function totalsByProduct(Builder $query, string $column, string $date, array $productIds): Collection
    {
        return $query
            ->whereIn('fuel_product_id', $productIds)
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
