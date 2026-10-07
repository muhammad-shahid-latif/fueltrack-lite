<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Models\FuelProduct;
use App\Models\FuelPurchase;
use App\Models\Sale;
use App\Models\StockAdjustment;
use App\Models\Supplier;
use App\Models\Tank;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Stock is derived from a ledger, never stored:
 * opening stock + purchases − sales ± adjustments = closing stock.
 *
 * Every delivery also updates the product's moving weighted average cost:
 * (stock on hand × current average + quantity × rate) ÷ (stock + quantity).
 */
class FuelStockService
{
    public function __construct(
        private DailyClosingService $closings,
        private TankService $tanks,
        private SupplierService $suppliers,
    ) {}

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
        ?Tank $tank = null,
        ?Supplier $supplier = null,
        DateTimeInterface|string|null $dueDate = null,
        float $paidNow = 0,
        PaymentMethod $paymentMethod = PaymentMethod::Cash,
    ): FuelPurchase {
        $date = Carbon::parse($date)->toDateString();

        return DB::transaction(function () use ($product, $date, $quantity, $rate, $supplierName, $invoiceNumber, $recordedBy, $notes, $tank, $supplier, $dueDate, $paidNow, $paymentMethod) {
            $this->closings->assertOpen($date);

            $tank = $this->resolveTank($product, $tank, $quantity, $date);
            $quantity = round($quantity, 2);
            $amount = round($quantity * $rate, 2);

            if ($paidNow > $amount + 0.005) {
                throw ValidationException::withMessages(['paid_now' => 'The amount paid cannot be more than the bill of Rs. '.number_format($amount, 2).'.']);
            }

            $purchase = FuelPurchase::create([
                'fuel_product_id' => $product->id,
                'tank_id' => $tank?->id,
                'supplier_id' => $supplier?->id,
                'business_date' => $date,
                'due_date' => $supplier ? Carbon::parse($dueDate ?? $this->suppliers->dueDateFor($supplier, $date))->toDateString() : null,
                'quantity' => $quantity,
                'rate' => round($rate, 2),
                'amount' => $amount,
                'average_cost' => $this->averageCostAfter($product, $date, $quantity, $rate),
                'supplier_name' => $supplier?->name ?? $supplierName,
                'invoice_number' => $invoiceNumber,
                'recorded_by' => $recordedBy?->id,
                'notes' => $notes,
            ]);

            if ($supplier && $paidNow > 0) {
                $this->suppliers->recordPayment(
                    supplier: $supplier,
                    amount: $paidNow,
                    method: $paymentMethod,
                    recordedBy: $recordedBy,
                    reference: $invoiceNumber ? 'Inv. '.$invoiceNumber : null,
                    notes: 'Paid on delivery',
                    date: $date,
                );
            }

            return $purchase;
        });
    }

    /**
     * Weighted average cost per unit once this delivery is added:
     * (stock on hand × current average + quantity × rate) ÷ (stock + quantity).
     */
    public function averageCostAfter(FuelProduct $product, DateTimeInterface|string $date, float $quantity, float $rate): float
    {
        $onHand = max(0.0, $this->currentStock($product, $date));
        $current = $product->currentAverageCost($date) ?? $rate;
        $total = $onHand + $quantity;

        return $total > 0 ? round(($onHand * $current + $quantity * $rate) / $total, 4) : round($rate, 4);
    }

    /**
     * Fuel with tanks must go into one of them, and must fit.
     *
     * @throws ValidationException
     */
    private function resolveTank(FuelProduct $product, ?Tank $tank, float $quantity, string $date): ?Tank
    {
        if (! $product->isMetered()) {
            return null;
        }

        if ($tank === null) {
            if ($product->tanks()->active()->exists()) {
                throw ValidationException::withMessages(['tank_id' => 'Choose the tank this delivery went into.']);
            }

            return null;
        }

        $tank = Tank::query()->lockForUpdate()->findOrFail($tank->id);

        if ($tank->fuel_product_id !== $product->id || ! $tank->is_active) {
            throw ValidationException::withMessages(['tank_id' => "{$tank->name} does not hold {$product->name}."]);
        }

        $room = $this->tanks->ullage($tank, $date);

        if ($quantity > $room + 0.005) {
            throw ValidationException::withMessages([
                'quantity' => "{$tank->name} has room for only ".number_format(max(0, $room), 2).' L.',
            ]);
        }

        return $tank;
    }

    /**
     * Stock movement for every active product, fuel first.
     *
     * @return Collection<int, array{product: FuelProduct, opening: float, received: float, sold: float, adjusted: float, closing: float, is_low: bool}>
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
        $adjusted = $this->totalsByProduct(StockAdjustment::query(), 'quantity', $date, $ids);

        return $products->map(function (FuelProduct $product) use ($received, $sold, $adjusted) {
            $in = $received->get($product->id);
            $out = $sold->get($product->id);
            $adj = $adjusted->get($product->id);

            $opening = round((float) $product->opening_stock + (float) ($in->before_date ?? 0) - (float) ($out->before_date ?? 0) + (float) ($adj->before_date ?? 0), 2);
            $receivedToday = round((float) ($in->on_date ?? 0), 2);
            $soldToday = round((float) ($out->on_date ?? 0), 2);
            $adjustedToday = round((float) ($adj->on_date ?? 0), 2);
            $closing = round($opening + $receivedToday - $soldToday + $adjustedToday, 2);

            return [
                'product' => $product,
                'opening' => $opening,
                'received' => $receivedToday,
                'sold' => $soldToday,
                'adjusted' => $adjustedToday,
                'closing' => $closing,
                'is_low' => $closing <= (float) $product->low_stock_threshold,
            ];
        })->values();
    }

    /**
     * One grouped query per ledger: totals before the date and on the date.
     *
     * @param  Builder<FuelPurchase>|Builder<Sale>|Builder<StockAdjustment>  $query
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
