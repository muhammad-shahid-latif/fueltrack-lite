<?php

namespace App\Support;

use App\Models\DailyClosing;
use App\Models\Expense;
use App\Models\FuelPrice;
use App\Models\FuelPurchase;
use App\Models\MeterReading;
use App\Models\Sale;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * The single source of "now" for business entries.
 *
 * Dates always come from the server clock, never from the browser or a form.
 * As a second line of defence, entries are refused when the server clock is
 * behind the newest saved record, which is what happens when someone winds
 * the computer's date back to post into an earlier day.
 */
final class BusinessClock
{
    /** Small allowance for normal clock drift / NTP corrections. */
    private const TOLERANCE_MINUTES = 5;

    public static function now(): Carbon
    {
        return now();
    }

    public static function today(): Carbon
    {
        return today();
    }

    /**
     * @throws ValidationException
     */
    public static function assertTrustworthy(): void
    {
        $latest = self::latestRecordedAt();

        if ($latest === null || self::now()->gte($latest->copy()->subMinutes(self::TOLERANCE_MINUTES))) {
            return;
        }

        throw ValidationException::withMessages([
            'clock' => sprintf(
                'The system date/time (%s) is earlier than the last saved entry (%s). Entries are blocked until the date and time are corrected.',
                self::now()->format('d M Y, h:i A'),
                $latest->format('d M Y, h:i A'),
            ),
        ]);
    }

    public static function latestRecordedAt(): ?Carbon
    {
        $latest = collect([
            MeterReading::class,
            Sale::class,
            FuelPurchase::class,
            Expense::class,
            DailyClosing::class,
            FuelPrice::class,
        ])->map(fn (string $model) => $model::query()->max('created_at'))
            ->filter()
            ->max();

        return $latest ? Carbon::parse($latest) : null;
    }
}
