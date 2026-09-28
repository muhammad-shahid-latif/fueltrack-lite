<?php

namespace App\Services;

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
        float $openingMeter,
        float $closingMeter,
        ?User $recordedBy = null,
        ?float $rate = null,
        ?string $notes = null,
    ): MeterReading {
        $date = Carbon::parse($date)->toDateString();

        return DB::transaction(function () use ($nozzle, $date, $openingMeter, $closingMeter, $recordedBy, $rate, $notes) {
            $this->closings->assertOpen($date);

            $nozzle = Nozzle::query()->with('fuelProduct')->lockForUpdate()->findOrFail($nozzle->id);

            $this->validateMeters($nozzle, $openingMeter, $closingMeter);

            $liters = round($closingMeter - $openingMeter, 2);
            $rate = round($rate ?? (float) $nozzle->fuelProduct->selling_price, 2);

            $reading = MeterReading::create([
                'nozzle_id' => $nozzle->id,
                'business_date' => $date,
                'opening_meter' => $openingMeter,
                'closing_meter' => $closingMeter,
                'liters' => $liters,
                'recorded_by' => $recordedBy?->id,
                'notes' => $notes,
            ]);

            $reading->sale()->create([
                'fuel_product_id' => $nozzle->fuel_product_id,
                'business_date' => $date,
                'liters' => $liters,
                'rate' => $rate,
                'amount' => round($liters * $rate, 2),
            ]);

            $nozzle->update(['current_meter' => $closingMeter]);

            return $reading;
        });
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
