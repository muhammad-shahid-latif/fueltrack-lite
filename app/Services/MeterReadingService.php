<?php

namespace App\Services;

use App\Enums\Shift;
use App\Models\MeterReading;
use App\Models\Nozzle;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MeterReadingService
{
    public function __construct(private DailyClosingService $closings) {}

    /**
     * Records a nozzle reading and its sale in one transaction:
     * liters = closing − opening, amount = liters × rate.
     *
     * The rate defaults to the product's current selling price and is
     * stored on the sale, so later price changes never alter history.
     *
     * @throws ValidationException
     */
    public function record(
        Nozzle $nozzle,
        DateTimeInterface|string $date,
        Shift $shift,
        float $openingMeter,
        float $closingMeter,
        ?User $recordedBy = null,
        ?float $rate = null,
        ?string $notes = null,
    ): MeterReading {
        $date = Carbon::parse($date)->toDateString();

        return DB::transaction(function () use ($nozzle, $date, $shift, $openingMeter, $closingMeter, $recordedBy, $rate, $notes) {
            $this->closings->assertOpen($date);

            $nozzle = Nozzle::query()->with(['fuelProduct', 'pump'])->lockForUpdate()->findOrFail($nozzle->id);

            $this->validateNozzle($nozzle);
            $this->validateMeters($nozzle, $openingMeter, $closingMeter);

            $liters = round($closingMeter - $openingMeter, 2);
            $rate = round($rate ?? (float) $nozzle->fuelProduct->selling_price, 2);

            $reading = MeterReading::create([
                'nozzle_id' => $nozzle->id,
                'business_date' => $date,
                'shift' => $shift,
                'opening_meter' => $openingMeter,
                'closing_meter' => $closingMeter,
                'liters' => $liters,
                'recorded_by' => $recordedBy?->id,
                'notes' => $notes,
            ]);

            $reading->sale()->create([
                'fuel_product_id' => $nozzle->fuel_product_id,
                'tank_id' => $nozzle->tank_id,
                'business_date' => $date,
                'quantity' => $liters,
                'rate' => $rate,
                'amount' => round($liters * $rate, 2),
                'recorded_by' => $recordedBy?->id,
            ]);

            $nozzle->update(['current_meter' => $closingMeter]);

            return $reading;
        });
    }

    /**
     * Corrects a reading's shift, notes and meters. The sale is recalculated
     * at the rate it was originally sold at. Meters stay a continuous chain:
     * the opening must match the previous closing, and a changed closing is
     * carried into the next reading's opening (and its sale).
     *
     * @throws ValidationException
     */
    public function update(
        MeterReading $reading,
        Shift $shift,
        float $openingMeter,
        float $closingMeter,
        ?string $notes = null,
    ): MeterReading {
        return DB::transaction(function () use ($reading, $shift, $openingMeter, $closingMeter, $notes) {
            $reading = MeterReading::query()->with('sale')->lockForUpdate()->findOrFail($reading->id);
            $this->closings->assertOpen($reading->business_date);

            $nozzle = Nozzle::query()->lockForUpdate()->findOrFail($reading->nozzle_id);
            $previous = $this->neighbour($reading, before: true);
            $next = $this->neighbour($reading, before: false);

            if ($previous && ! $this->matches($openingMeter, (float) $previous->closing_meter)) {
                throw ValidationException::withMessages([
                    'opening_meter' => 'Opening meter must match the previous closing reading ('.number_format((float) $previous->closing_meter, 2).').',
                ]);
            }

            if ($closingMeter <= $openingMeter) {
                throw ValidationException::withMessages([
                    'closing_meter' => 'Closing meter must be greater than the opening meter.',
                ]);
            }

            if ($next) {
                $this->carryIntoNext($next, $closingMeter);
            } else {
                $nozzle->update(['current_meter' => $closingMeter]);
            }

            $reading->fill(['shift' => $shift, 'notes' => $notes]);
            $this->applyMeters($reading, $openingMeter, $closingMeter);

            return $reading;
        });
    }

    /**
     * Deletes a reading with its sale and puts the nozzle meter back to the
     * reading's opening value. Only the nozzle's latest reading can go, so
     * the meter chain never has a gap.
     *
     * @throws ValidationException
     */
    public function delete(MeterReading $reading): void
    {
        DB::transaction(function () use ($reading) {
            $reading = MeterReading::query()->with('nozzle.pump')->lockForUpdate()->findOrFail($reading->id);
            $this->closings->assertOpen($reading->business_date);

            $nozzle = Nozzle::query()->lockForUpdate()->findOrFail($reading->nozzle_id);

            if ($next = $this->neighbour($reading, before: false)) {
                throw ValidationException::withMessages([
                    'record' => sprintf(
                        'Only the latest reading of %s can be deleted. Delete the later reading from %s first.',
                        $reading->nozzle->label(),
                        $next->business_date->format('d M Y'),
                    ),
                ]);
            }

            $reading->sale()->delete();
            $reading->delete();

            $nozzle->update(['current_meter' => $reading->opening_meter]);
        });
    }

    /**
     * The reading recorded just before or after this one on the same nozzle.
     */
    public function neighbour(MeterReading $reading, bool $before): ?MeterReading
    {
        return MeterReading::query()
            ->with('sale')
            ->where('nozzle_id', $reading->nozzle_id)
            ->where('id', $before ? '<' : '>', $reading->id)
            ->orderBy('id', $before ? 'desc' : 'asc')
            ->first();
    }

    /**
     * @throws ValidationException
     */
    private function carryIntoNext(MeterReading $next, float $closingMeter): void
    {
        if ($this->matches($closingMeter, (float) $next->opening_meter)) {
            return;
        }

        if ($closingMeter >= (float) $next->closing_meter) {
            throw ValidationException::withMessages([
                'closing_meter' => sprintf(
                    'Closing meter must stay below the next reading\'s closing meter (%s on %s).',
                    number_format((float) $next->closing_meter, 2),
                    $next->business_date->format('d M Y'),
                ),
            ]);
        }

        if ($this->closings->isClosed($next->business_date)) {
            throw ValidationException::withMessages([
                'closing_meter' => sprintf(
                    'This also changes the next reading on %s, but that day is closed. Reopen it first.',
                    $next->business_date->format('d M Y'),
                ),
            ]);
        }

        $this->applyMeters($next, $closingMeter, (float) $next->closing_meter);
    }

    /**
     * Saves the meters and recalculates the linked sale at its original rate.
     */
    private function applyMeters(MeterReading $reading, float $openingMeter, float $closingMeter): void
    {
        $liters = round($closingMeter - $openingMeter, 2);

        $reading->fill([
            'opening_meter' => $openingMeter,
            'closing_meter' => $closingMeter,
            'liters' => $liters,
        ])->save();

        $reading->sale->update([
            'quantity' => $liters,
            'amount' => round($liters * (float) $reading->sale->rate, 2),
        ]);
    }

    private function matches(float $a, float $b): bool
    {
        return abs($a - $b) < 0.005;
    }

    /**
     * @throws ValidationException
     */
    private function validateNozzle(Nozzle $nozzle): void
    {
        if (! $nozzle->is_active || ! $nozzle->pump->is_active) {
            throw ValidationException::withMessages(['nozzle_id' => 'This nozzle or its pump is inactive.']);
        }

        if (! $nozzle->fuelProduct->isMetered()) {
            throw ValidationException::withMessages(['nozzle_id' => 'This nozzle is not linked to a fuel product.']);
        }
    }

    /**
     * @throws ValidationException
     */
    private function validateMeters(Nozzle $nozzle, float $openingMeter, float $closingMeter): void
    {
        $lastClosing = (float) $nozzle->current_meter;

        if ($lastClosing > 0 && abs($openingMeter - $lastClosing) >= 0.005) {
            throw ValidationException::withMessages([
                'opening_meter' => 'Opening meter must match the last closing reading ('.number_format($lastClosing, 2).').',
            ]);
        }

        if ($closingMeter <= $openingMeter) {
            throw ValidationException::withMessages([
                'closing_meter' => 'Closing meter must be greater than the opening meter.',
            ]);
        }
    }
}
