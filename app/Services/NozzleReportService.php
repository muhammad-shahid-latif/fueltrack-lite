<?php

namespace App\Services;

use App\Models\MeterReading;
use App\Models\Nozzle;
use App\Support\BusinessClock;
use App\Support\ShiftSchedule;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Nozzle operations: the live board (current meters and today's sales per
 * nozzle) and performance over a period.
 */
class NozzleReportService
{
    /**
     * @return Collection<int, array{nozzle: Nozzle, liters: float, amount: float, readings: int, shifts: list<string>, last_at: ?Carbon, last_by: ?string, share: float}>
     */
    public function live(DateTimeInterface|string|null $date = null): Collection
    {
        $date = Carbon::parse($date ?? BusinessClock::today())->toDateString();

        $nozzles = Nozzle::inDisplayOrder(Nozzle::query()->with(['pump', 'fuelProduct', 'tank'])->get());

        $readings = MeterReading::query()
            ->with(['sale', 'recorder'])
            ->whereDate('business_date', $date)
            ->orderBy('id')
            ->get()
            ->groupBy('nozzle_id');

        $total = (float) $readings->flatten()->sum('liters');

        return $nozzles->map(function (Nozzle $nozzle) use ($readings, $total) {
            $own = $readings->get($nozzle->id, collect());
            $liters = round((float) $own->sum('liters'), 2);
            $last = $own->last();

            return [
                'nozzle' => $nozzle,
                'liters' => $liters,
                'amount' => round((float) $own->sum(fn (MeterReading $reading) => (float) $reading->sale?->amount), 2),
                'readings' => $own->count(),
                'shifts' => $own->map(fn (MeterReading $reading) => $reading->shift->value)->unique()->values()->all(),
                'last_at' => $last?->created_at,
                'last_by' => $last?->recorder?->name,
                'share' => $total > 0 ? round($liters / $total * 100, 1) : 0.0,
            ];
        })->values();
    }

    /**
     * Daily liters and sales per nozzle over a period, with totals and the
     * best day of each nozzle.
     *
     * @return array{days: list<Carbon>, rows: Collection<int, array<string, mixed>>, totals: array{liters: float, amount: float}, daily_totals: array<string, float>, max_cell: float}
     */
    public function performance(DateTimeInterface|string $from, DateTimeInterface|string $to): array
    {
        $from = Carbon::parse($from)->startOfDay();
        $to = Carbon::parse($to)->startOfDay();

        $cells = MeterReading::query()
            ->join('sales', 'sales.meter_reading_id', '=', 'meter_readings.id')
            ->whereDate('meter_readings.business_date', '>=', $from)
            ->whereDate('meter_readings.business_date', '<=', $to)
            ->groupBy('meter_readings.nozzle_id', 'meter_readings.business_date')
            ->selectRaw('meter_readings.nozzle_id as nozzle_id, meter_readings.business_date as day, SUM(meter_readings.liters) as liters, SUM(sales.amount) as amount, COUNT(*) as readings')
            ->toBase()
            ->get()
            ->groupBy('nozzle_id');

        $days = [];

        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $days[] = $day->copy();
        }

        $nozzles = Nozzle::inDisplayOrder(Nozzle::query()->with(['pump', 'fuelProduct'])->get());

        $grand = (float) $cells->flatten()->sum('liters');
        $dailyTotals = array_fill_keys(array_map(fn (Carbon $day) => $day->toDateString(), $days), 0.0);
        $maxCell = 0.0;

        $rows = $nozzles->map(function (Nozzle $nozzle) use ($cells, $days, $grand, &$dailyTotals, &$maxCell) {
            $byDay = collect($cells->get($nozzle->id, []))->keyBy(fn (object $cell) => Carbon::parse($cell->day)->toDateString());
            $daily = [];

            foreach ($days as $day) {
                $key = $day->toDateString();
                $liters = round((float) ($byDay->get($key)->liters ?? 0), 2);
                $daily[$key] = $liters;
                $dailyTotals[$key] = round($dailyTotals[$key] + $liters, 2);
                $maxCell = max($maxCell, $liters);
            }

            $liters = round(array_sum($daily), 2);
            $activeDays = count(array_filter($daily, fn (float $value) => $value > 0));
            $bestKey = $liters > 0 ? array_search(max($daily), $daily, true) : null;

            return [
                'nozzle' => $nozzle,
                'daily' => $daily,
                'liters' => $liters,
                'amount' => round((float) $byDay->sum('amount'), 2),
                'readings' => (int) $byDay->sum('readings'),
                'avg_per_day' => $activeDays > 0 ? round($liters / $activeDays, 2) : 0.0,
                'share' => $grand > 0 ? round($liters / $grand * 100, 1) : 0.0,
                'best_day' => $bestKey ? Carbon::parse($bestKey) : null,
                'best_liters' => $bestKey ? $daily[$bestKey] : 0.0,
            ];
        })->sortByDesc('liters')->values();

        return [
            'days' => $days,
            'rows' => $rows,
            'totals' => ['liters' => round($rows->sum('liters'), 2), 'amount' => round($rows->sum('amount'), 2)],
            'daily_totals' => $dailyTotals,
            'max_cell' => $maxCell,
        ];
    }

    /**
     * @return list<string>
     */
    public static function shiftOrder(): array
    {
        return ShiftSchedule::activeValues();
    }
}
