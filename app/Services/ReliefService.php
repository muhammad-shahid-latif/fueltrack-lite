<?php

namespace App\Services;

use App\Models\FuelProduct;
use App\Models\ReliefSale;
use App\Models\Sale;
use App\Models\User;
use App\Support\BusinessClock;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * PM Fuel Relief Scheme, one total per day and fuel. Tokens are handled in
 * the government system; before closing the day, the station enters the
 * tokens redeemed and the relief amount. The meters count all fuel at the
 * pump price, so the relief is taken off the day's cash and kept as an
 * amount to claim from the government until it is marked received.
 */
class ReliefService
{
    public function __construct(private DailyClosingService $closings) {}

    /**
     * Saves the day's relief total for one fuel, as shown in the government
     * system: tokens redeemed and the relief amount. A token gives a fixed
     * amount (Rs. 500) whatever the customer fills, so the amount drives the
     * claim; the liters are only the amount at the pump price, for stock and
     * reports. Saving again for the same day and fuel replaces the total.
     *
     * @throws ValidationException
     */
    public function saveDay(
        DateTimeInterface|string $date,
        FuelProduct $product,
        int $tokens,
        float $amount,
        ?User $recordedBy = null,
        ?string $notes = null,
    ): ReliefSale {
        $date = Carbon::parse($date)->toDateString();
        $price = (float) $product->selling_price;

        if (! $product->isMetered() || $price <= 0) {
            throw ValidationException::withMessages(['fuel_product_id' => 'Relief is only given on fuel with a price.']);
        }

        return DB::transaction(function () use ($date, $product, $tokens, $amount, $recordedBy, $notes, $price) {
            $this->closings->assertOpen($date);

            $existing = ReliefSale::query()
                ->whereDate('business_date', $date)
                ->where('fuel_product_id', $product->id)
                ->lockForUpdate()
                ->orderBy('id')
                ->get();

            if ($existing->contains(fn (ReliefSale $row) => $row->isReceived())) {
                throw ValidationException::withMessages(['amount' => 'This day is already marked as received from the government. Undo that first.']);
            }

            $values = [
                'business_date' => $date,
                'fuel_product_id' => $product->id,
                'tokens' => $tokens,
                'quantity' => self::litersFor($amount, $price),
                'quota_liters' => 0,
                'price' => $price,
                'relief_rate' => $tokens > 0 ? round($amount / $tokens, 2) : 0,
                'relief_amount' => round($amount, 2),
                'recorded_by' => $recordedBy?->id,
                'notes' => $notes,
            ];

            // One total per day and fuel: update it, and fold any older rows into it.
            $row = $existing->shift();
            $existing->each->delete();

            if ($row) {
                $row->update($values);

                return $row;
            }

            return ReliefSale::create($values);
        });
    }

    /** Liters a relief amount buys at the pump price. */
    public static function litersFor(float $amount, float $price): float
    {
        return $price > 0 ? round($amount / $price, 2) : 0.0;
    }

    /** Value of a fuel sold on a day by the meter readings. */
    public static function meterAmountOn(DateTimeInterface|string $date, FuelProduct $product): float
    {
        return round((float) Sale::query()
            ->whereDate('business_date', Carbon::parse($date)->toDateString())
            ->where('fuel_product_id', $product->id)
            ->sum('amount'), 2);
    }

    /** Liters of a fuel sold on a day by the meter readings. */
    public static function meterLitersOn(DateTimeInterface|string $date, FuelProduct $product): float
    {
        return round((float) Sale::query()
            ->whereDate('business_date', Carbon::parse($date)->toDateString())
            ->where('fuel_product_id', $product->id)
            ->sum('quantity'), 2);
    }

    /**
     * @throws ValidationException
     */
    public function delete(ReliefSale $sale): void
    {
        DB::transaction(function () use ($sale) {
            $sale = ReliefSale::query()->lockForUpdate()->findOrFail($sale->id);

            if ($sale->isReceived()) {
                throw ValidationException::withMessages(['record' => 'This relief is already marked as received from the government. Undo that first.']);
            }

            $this->closings->assertOpen($sale->business_date, 'record');

            $sale->delete();
        });
    }

    /**
     * Marks a day's relief as paid by the government. Returns the amount marked.
     */
    public function markReceived(DateTimeInterface|string $date, DateTimeInterface|string $receivedOn, ?string $reference = null): float
    {
        BusinessClock::assertTrustworthy();

        $date = Carbon::parse($date)->toDateString();
        $query = ReliefSale::query()->pending()->whereDate('business_date', $date);
        $amount = round((float) (clone $query)->sum('relief_amount'), 2);

        // Row by row so every change is written to the audit trail.
        DB::transaction(fn () => $query->get()->each->update([
            'received_on' => Carbon::parse($receivedOn)->toDateString(),
            'receipt_reference' => $reference,
        ]));

        return $amount;
    }

    public function undoReceived(DateTimeInterface|string $date): void
    {
        BusinessClock::assertTrustworthy();

        DB::transaction(fn () => ReliefSale::query()
            ->whereDate('business_date', Carbon::parse($date)->toDateString())
            ->whereNotNull('received_on')
            ->get()
            ->each->update(['received_on' => null, 'receipt_reference' => null]));
    }

    /**
     * Relief of a business day: tokens, the relief amount, and the liters
     * that amount buys at the pump price.
     *
     * @return array{tokens: int, liters: float, amount: float}
     */
    public static function totalsOn(string $date): array
    {
        $row = ReliefSale::query()
            ->whereDate('business_date', $date)
            ->selectRaw('COALESCE(SUM(tokens), 0) as tokens, COALESCE(SUM(quantity), 0) as liters, COALESCE(SUM(relief_amount), 0) as amount')
            ->toBase()
            ->first();

        return ['tokens' => (int) $row->tokens, 'liters' => round((float) $row->liters, 2), 'amount' => round((float) $row->amount, 2)];
    }

    /** Relief still to be paid by the government, all days. */
    public static function pendingTotal(): float
    {
        return round((float) ReliefSale::query()->pending()->sum('relief_amount'), 2);
    }

    /**
     * One row per business day: what to claim and whether it was received.
     *
     * @return Collection<int, object{date: Carbon, tokens: int, liters: float, amount: float, pending: float, received_on: ?Carbon, reference: ?string}>
     */
    public function claimDays(int $limit = 30): Collection
    {
        return ReliefSale::query()
            ->groupBy('business_date')
            ->select('business_date')
            ->selectRaw('SUM(tokens) as tokens, SUM(quantity) as liters, SUM(relief_amount) as amount')
            ->selectRaw('SUM(CASE WHEN received_on IS NULL THEN relief_amount ELSE 0 END) as pending')
            ->selectRaw('MAX(received_on) as received_on, MAX(receipt_reference) as reference')
            ->orderByDesc('business_date')
            ->limit($limit)
            ->toBase()
            ->get()
            ->map(fn (object $row) => (object) [
                'date' => Carbon::parse($row->business_date),
                'tokens' => (int) $row->tokens,
                'liters' => round((float) $row->liters, 2),
                'amount' => round((float) $row->amount, 2),
                'pending' => round((float) $row->pending, 2),
                'received_on' => $row->received_on ? Carbon::parse($row->received_on) : null,
                'reference' => $row->reference,
            ]);
    }
}
