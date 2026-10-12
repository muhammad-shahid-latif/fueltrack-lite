<?php

namespace App\Services;

use App\Enums\Shift;
use App\Enums\StockAdjustmentType;
use App\Models\FuelPurchase;
use App\Models\Sale;
use App\Models\StockAdjustment;
use App\Models\Tank;
use App\Models\User;
use App\Support\BusinessClock;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Tank levels come from each tank's own ledger:
 *
 *   opening stock + deliveries − nozzle sales ± adjustments = level
 *
 * Dip readings correct the book level to the measured one, and transfers
 * move fuel between two tanks of the same product.
 */
class TankService
{
    /** Days of sales used for the "days of cover" estimate. */
    private const COVER_WINDOW_DAYS = 7;

    public function __construct(private DailyClosingService $closings) {}

    /**
     * Live picture of every tank as of a date (end of that day).
     *
     * @return Collection<int, array{tank: Tank, level: float, capacity: float, percent: float, ullage: float, status: string, received_today: float, sold_today: float, avg_daily: float, days_cover: ?float, last_dip: ?StockAdjustment}>
     */
    public function levels(DateTimeInterface|string|null $date = null, bool $activeOnly = false): Collection
    {
        $date = Carbon::parse($date ?? BusinessClock::today())->toDateString();

        $tanks = Tank::query()
            ->with('fuelProduct')
            ->withCount('nozzles')
            ->when($activeOnly, fn ($query) => $query->active())
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        if ($tanks->isEmpty()) {
            return collect();
        }

        $ids = $tanks->pluck('id')->all();
        $totals = $this->totals($ids, $date);
        $windowStart = Carbon::parse($date)->subDays(self::COVER_WINDOW_DAYS - 1)->toDateString();

        $windowSales = Sale::query()
            ->whereIn('tank_id', $ids)
            ->whereDate('business_date', '>=', $windowStart)
            ->whereDate('business_date', '<=', $date)
            ->groupBy('tank_id')
            ->selectRaw('tank_id, SUM(quantity) as quantity, COUNT(DISTINCT business_date) as days')
            ->toBase()
            ->get()
            ->keyBy('tank_id');

        $lastDips = StockAdjustment::query()
            ->whereIn('tank_id', $ids)
            ->where('type', StockAdjustmentType::Dip->value)
            ->whereDate('business_date', '<=', $date)
            ->orderByDesc('id')
            ->get()
            ->unique('tank_id')
            ->keyBy('tank_id');

        return $tanks->map(function (Tank $tank) use ($totals, $windowSales, $lastDips) {
            $row = $totals[$tank->id];
            $capacity = (float) $tank->capacity;
            $level = $row['level'];
            $window = $windowSales->get($tank->id);
            $avgDaily = $window && $window->days > 0 ? round((float) $window->quantity / (int) $window->days, 2) : 0.0;
            $percent = $capacity > 0 ? round(max(0, $level) / $capacity * 100, 1) : 0.0;

            return [
                'tank' => $tank,
                'level' => $level,
                'capacity' => $capacity,
                'percent' => min(100.0, $percent),
                'ullage' => round(max(0, $capacity - $level), 2),
                'status' => $this->status($percent / 100),
                'received_today' => $row['received_today'],
                'sold_today' => $row['sold_today'],
                'avg_daily' => $avgDaily,
                'days_cover' => $avgDaily > 0 ? round(max(0, $level) / $avgDaily, 1) : null,
                'last_dip' => $lastDips->get($tank->id),
            ];
        })->values();
    }

    public function level(Tank $tank, DateTimeInterface|string|null $date = null): float
    {
        $date = Carbon::parse($date ?? BusinessClock::today())->toDateString();

        return $this->totals([$tank->id], $date)[$tank->id]['level'];
    }

    /**
     * Every movement of one tank in a period with a running balance.
     *
     * @return array{opening: float, closing: float, received: float, sold: float, adjusted: float, rows: Collection<int, array{date: Carbon, at: Carbon, type: string, icon: string, label: string, detail: ?string, quantity: float, balance: float, by: ?string}>}
     */
    public function ledger(Tank $tank, DateTimeInterface|string $from, DateTimeInterface|string $to): array
    {
        $from = Carbon::parse($from)->startOfDay();
        $to = Carbon::parse($to)->startOfDay();
        $opening = $this->level($tank, $from->copy()->subDay());

        $deliveries = FuelPurchase::query()
            ->with(['supplier', 'recorder'])
            ->where('tank_id', $tank->id)
            ->whereDate('business_date', '>=', $from)
            ->whereDate('business_date', '<=', $to)
            ->get()
            ->map(fn (FuelPurchase $purchase) => [
                'date' => $purchase->business_date,
                'at' => $purchase->created_at,
                'type' => 'delivery',
                'icon' => 'truck',
                'label' => 'Delivery received',
                'detail' => collect([$purchase->supplierLabel(), $purchase->invoice_number ? 'Inv. '.$purchase->invoice_number : null, 'Rs. '.number_format((float) $purchase->rate, 2).'/L'])->filter()->join(' · '),
                'quantity' => (float) $purchase->quantity,
                'by' => $purchase->recorder?->name,
            ]);

        $sales = Sale::query()
            ->join('meter_readings', 'meter_readings.id', '=', 'sales.meter_reading_id')
            ->where('sales.tank_id', $tank->id)
            ->whereDate('sales.business_date', '>=', $from)
            ->whereDate('sales.business_date', '<=', $to)
            ->groupBy('sales.business_date', 'meter_readings.shift')
            ->selectRaw('sales.business_date as business_date, meter_readings.shift as shift, SUM(sales.quantity) as quantity, COUNT(*) as readings, MAX(sales.created_at) as at')
            ->toBase()
            ->get()
            ->map(fn (object $row) => [
                'date' => Carbon::parse($row->business_date),
                'at' => Carbon::parse($row->at),
                'type' => 'sales',
                'icon' => 'fuel-pump',
                'label' => (Shift::tryFrom((string) $row->shift)?->label() ?? 'Shift').' shift sales',
                'detail' => $row->readings.' '.str('nozzle reading')->plural((int) $row->readings),
                'quantity' => -round((float) $row->quantity, 2),
                'by' => null,
            ]);

        $adjustments = StockAdjustment::query()
            ->with('recorder')
            ->where('tank_id', $tank->id)
            ->whereDate('business_date', '>=', $from)
            ->whereDate('business_date', '<=', $to)
            ->get()
            ->map(fn (StockAdjustment $adjustment) => [
                'date' => $adjustment->business_date,
                'at' => $adjustment->created_at,
                'type' => $adjustment->type->value,
                'icon' => $adjustment->type->icon(),
                'label' => $adjustment->type->label(),
                'detail' => $adjustment->type === StockAdjustmentType::Dip
                    ? 'Dip '.number_format((float) $adjustment->dip_reading, 2).' L vs book '.number_format((float) $adjustment->book_stock, 2).' L'.($adjustment->notes ? ' · '.$adjustment->notes : '')
                    : $adjustment->notes,
                'quantity' => (float) $adjustment->quantity,
                'by' => $adjustment->recorder?->name,
            ]);

        $balance = $opening;

        $rows = $deliveries->concat($sales)->concat($adjustments)
            ->sortBy(fn (array $row) => $row['date']->toDateString().' '.$row['at']->format('H:i:s.u'))
            ->values()
            ->map(function (array $row) use (&$balance) {
                $balance = round($balance + $row['quantity'], 2);

                return [...$row, 'balance' => $balance];
            });

        return [
            'opening' => $opening,
            'closing' => $balance,
            'received' => round($rows->where('type', 'delivery')->sum('quantity'), 2),
            'sold' => round(-$rows->where('type', 'sales')->sum('quantity'), 2),
            'adjusted' => round($rows->whereNotIn('type', ['delivery', 'sales'])->sum('quantity'), 2),
            'rows' => $rows,
        ];
    }

    /**
     * Records a physical dip reading and corrects the book level to it.
     * A zero variance is still saved as proof the tank was checked.
     *
     * @throws ValidationException
     */
    public function recordDip(Tank $tank, float $measured, ?User $recordedBy = null, ?string $notes = null, DateTimeInterface|string|null $date = null): StockAdjustment
    {
        $date = Carbon::parse($date ?? BusinessClock::today())->toDateString();

        return DB::transaction(function () use ($tank, $measured, $recordedBy, $notes, $date) {
            $this->closings->assertOpen($date);
            $tank = Tank::query()->lockForUpdate()->findOrFail($tank->id);

            if ($measured > (float) $tank->capacity + 0.005) {
                throw ValidationException::withMessages([
                    'dip_reading' => 'The dip cannot be more than the tank capacity of '.number_format((float) $tank->capacity).' L.',
                ]);
            }

            $book = $this->level($tank, $date);

            return StockAdjustment::create([
                'fuel_product_id' => $tank->fuel_product_id,
                'tank_id' => $tank->id,
                'business_date' => $date,
                'type' => StockAdjustmentType::Dip,
                'quantity' => round($measured - $book, 2),
                'dip_reading' => round($measured, 2),
                'book_stock' => $book,
                'recorded_by' => $recordedBy?->id,
                'notes' => $notes,
            ]);
        });
    }

    /**
     * Moves fuel from one tank to another tank of the same product.
     *
     * @throws ValidationException
     */
    public function transfer(Tank $from, Tank $to, float $quantity, ?User $recordedBy = null, ?string $notes = null, DateTimeInterface|string|null $date = null): string
    {
        $date = Carbon::parse($date ?? BusinessClock::today())->toDateString();

        return DB::transaction(function () use ($from, $to, $quantity, $recordedBy, $notes, $date) {
            $this->closings->assertOpen($date);

            $from = Tank::query()->lockForUpdate()->findOrFail($from->id);
            $to = Tank::query()->with('fuelProduct')->lockForUpdate()->findOrFail($to->id);

            if ($from->is($to)) {
                throw ValidationException::withMessages(['to_tank_id' => 'Choose a different tank to transfer into.']);
            }

            if ((int) $from->fuel_product_id !== (int) $to->fuel_product_id) {
                throw ValidationException::withMessages(['to_tank_id' => "{$to->name} holds {$to->fuelProduct->name}. Fuel can only move between tanks of the same product."]);
            }

            $available = $this->level($from, $date);
            $room = (float) $to->capacity - $this->level($to, $date);

            if ($quantity > $available + 0.005) {
                throw ValidationException::withMessages(['quantity' => "{$from->name} has only ".number_format(max(0, $available), 2).' L.']);
            }

            if ($quantity > $room + 0.005) {
                throw ValidationException::withMessages(['quantity' => "{$to->name} has room for only ".number_format(max(0, $room), 2).' L.']);
            }

            $reference = 'TRF-'.Str::upper(Str::random(8));

            foreach ([[$from, -$quantity, StockAdjustmentType::TransferOut, "To {$to->name}"], [$to, $quantity, StockAdjustmentType::TransferIn, "From {$from->name}"]] as [$tank, $signed, $type, $label]) {
                StockAdjustment::create([
                    'fuel_product_id' => $tank->fuel_product_id,
                    'tank_id' => $tank->id,
                    'business_date' => $date,
                    'type' => $type,
                    'quantity' => round($signed, 2),
                    'reference' => $reference,
                    'recorded_by' => $recordedBy?->id,
                    'notes' => trim($label.($notes ? ' · '.$notes : '')),
                ]);
            }

            return $reference;
        });
    }

    /**
     * Room left in a tank, used to stop deliveries that would overflow it.
     */
    public function ullage(Tank $tank, DateTimeInterface|string|null $date = null): float
    {
        return round((float) $tank->capacity - $this->level($tank, $date), 2);
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array{level: float, received_today: float, sold_today: float}>
     */
    private function totals(array $ids, string $date): array
    {
        $sum = fn ($query, string $column) => $query
            ->whereIn('tank_id', $ids)
            ->whereDate('business_date', '<=', $date)
            ->groupBy('tank_id')
            ->select('tank_id')
            ->selectRaw("COALESCE(SUM({$column}), 0) as total")
            ->selectRaw("COALESCE(SUM(CASE WHEN DATE(business_date) = ? THEN {$column} ELSE 0 END), 0) as on_date", [$date])
            ->toBase()
            ->get()
            ->keyBy('tank_id');

        $in = $sum(FuelPurchase::query(), 'quantity');
        $out = $sum(Sale::query(), 'quantity');
        $adjusted = $sum(StockAdjustment::query(), 'quantity');
        $openings = Tank::query()->whereKey($ids)->pluck('opening_stock', 'id');

        $result = [];

        foreach ($ids as $id) {
            $result[$id] = [
                'level' => round((float) $openings[$id] + (float) ($in->get($id)->total ?? 0) - (float) ($out->get($id)->total ?? 0) + (float) ($adjusted->get($id)->total ?? 0), 2),
                'received_today' => round((float) ($in->get($id)->on_date ?? 0), 2),
                'sold_today' => round((float) ($out->get($id)->on_date ?? 0), 2),
            ];
        }

        return $result;
    }

    private function status(float $ratio): string
    {
        return match (true) {
            $ratio <= Tank::CRITICAL_RATIO => 'critical',
            $ratio <= Tank::LOW_RATIO => 'low',
            default => 'ok',
        };
    }
}
