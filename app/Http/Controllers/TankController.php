<?php

namespace App\Http\Controllers;

use App\Enums\StockAdjustmentType;
use App\Http\Requests\RecordDipRequest;
use App\Http\Requests\SaveTankRequest;
use App\Http\Requests\TransferFuelRequest;
use App\Models\FuelProduct;
use App\Models\StockAdjustment;
use App\Models\Tank;
use App\Services\DailyClosingService;
use App\Services\FuelStockService;
use App\Services\TankService;
use App\Support\BusinessClock;
use App\Support\Format;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TankController extends Controller
{
    /** Liters below which a dip counts as matching the system stock. */
    private const DIP_MATCH_TOLERANCE = 0.005;

    public function index(TankService $tanks): View
    {
        $levels = $tanks->levels();

        return view('tanks.index', [
            'levels' => $levels,
            'products' => $levels->groupBy(fn (array $row) => $row['tank']->fuel_product_id)->map(fn ($rows) => [
                'product' => $rows->first()['tank']->fuelProduct,
                'level' => round($rows->sum('level'), 2),
                'capacity' => round($rows->sum('capacity'), 2),
                'average_cost' => $rows->first()['tank']->fuelProduct->currentAverageCost(),
            ])->values(),
            'allTanks' => $levels->pluck('tank')->filter->is_active->values(),
        ]);
    }

    /**
     * Polled by the tank page every few seconds for live levels.
     */
    public function levels(TankService $tanks): JsonResponse
    {
        return response()->json([
            'at' => BusinessClock::now()->format('h:i:s A'),
            'tanks' => $tanks->levels()->map(fn (array $row) => $this->levelPayload($row))->values(),
        ]);
    }

    public function show(Request $request, Tank $tank, TankService $tanks): View
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = $request->date('from') ?? BusinessClock::today()->subDays(6);
        $to = $request->date('to') ?? BusinessClock::today();
        $tank->load(['fuelProduct', 'nozzles.pump']);
        $levels = $tanks->levels();

        return view('tanks.show', [
            'tank' => $tank,
            'level' => $levels->firstWhere('tank.id', $tank->id),
            // Same-fuel tanks this one can transfer into, with their live level and room.
            'targets' => $levels
                ->filter(fn (array $row) => $row['tank']->is_active && $row['tank']->fuel_product_id === $tank->fuel_product_id && $row['tank']->id !== $tank->id)
                ->map(fn (array $row) => ['id' => $row['tank']->id, 'name' => $row['tank']->name, 'level' => $row['level'], 'room' => $row['ullage']])
                ->values(),
            'recentTransfers' => StockAdjustment::query()
                ->with('recorder')
                ->where('tank_id', $tank->id)
                ->whereIn('type', [StockAdjustmentType::TransferIn->value, StockAdjustmentType::TransferOut->value])
                ->latest('id')
                ->limit(5)
                ->get(),
            'ledger' => $tanks->ledger($tank, $from, $to),
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function create(FuelStockService $stock): View
    {
        $fuels = FuelProduct::query()->fuel()->active()->withCount('tanks')->orderBy('id')->get();

        return view('tanks.form', [
            'tank' => new Tank(['is_active' => true, 'capacity' => 25000]),
            'fuels' => $fuels,
            // Suggested opening level: the product's book stock not yet in a tank.
            'suggested' => $fuels->mapWithKeys(fn (FuelProduct $fuel) => [
                $fuel->id => $fuel->tanks_count === 0 ? max(0, $stock->currentStock($fuel, BusinessClock::today())) : 0,
            ]),
        ]);
    }

    public function store(SaveTankRequest $request): RedirectResponse
    {
        $tank = Tank::create([
            'name' => $request->string('name')->trim()->toString(),
            'fuel_product_id' => $request->integer('fuel_product_id'),
            'capacity' => $request->input('capacity'),
            'opening_stock' => $request->input('opening_stock'),
            'is_active' => true,
        ]);

        return redirect()->route('tanks.show', $tank)->with('success', "{$tank->name} added. Link its nozzles from Pumps & nozzles.");
    }

    public function edit(Tank $tank): View
    {
        return view('tanks.form', [
            'tank' => $tank,
            'fuels' => FuelProduct::query()->fuel()->active()->orderBy('id')->get(),
            'suggested' => collect(),
        ]);
    }

    public function update(SaveTankRequest $request, Tank $tank): RedirectResponse
    {
        $tank->update([
            'name' => $request->string('name')->trim()->toString(),
            'capacity' => $request->input('capacity'),
            'is_active' => $request->boolean('is_active'),
            ...($request->has('fuel_product_id') ? ['fuel_product_id' => $request->integer('fuel_product_id')] : []),
        ]);

        return redirect()->route('tanks.show', $tank)->with('success', "{$tank->name} saved.");
    }

    public function destroy(Tank $tank): RedirectResponse
    {
        if ($tank->hasHistory()) {
            return back()->with('danger', "{$tank->name} has deliveries or sales on record, so it cannot be deleted. Mark it inactive instead.");
        }

        $tank->nozzles()->update(['tank_id' => null]);
        $tank->delete();

        return redirect()->route('tanks.index')->with('success', "{$tank->name} removed.");
    }

    /**
     * Live book stock and recent dips for the dip-reading popup.
     */
    public function dips(Tank $tank, TankService $tanks, DailyClosingService $closings): JsonResponse
    {
        $tank->load('fuelProduct');
        $book = $tanks->level($tank);

        return response()->json([
            'tank' => [
                'id' => $tank->id,
                'name' => $tank->name,
                'fuel' => $tank->fuelProduct->name,
                'capacity' => (float) $tank->capacity,
                'capacity_label' => Format::liters($tank->capacity),
            ],
            'book' => $book,
            'book_label' => Format::liters($book),
            'at' => BusinessClock::now()->format('h:i:s A'),
            'day_closed' => $closings->isClosed(BusinessClock::today()),
            'history' => $tanks->recentDips($tank)->map(fn (StockAdjustment $dip) => $this->dipPayload($dip))->values(),
        ]);
    }

    public function dip(RecordDipRequest $request, Tank $tank, TankService $tanks): RedirectResponse|JsonResponse
    {
        $dip = $tanks->recordDip($tank, (float) $request->input('dip_reading'), $request->user(), $request->input('notes'));
        $dip->load('recorder:id,name');
        $variance = (float) $dip->quantity;
        $matches = abs($variance) < self::DIP_MATCH_TOLERANCE;

        $message = $matches
            ? sprintf('Readings match. %s dip equals the system stock of %s.', $tank->name, Format::liters($dip->dip_reading))
            : sprintf(
                '%s: dip %s vs system %s. %s of %s; system stock corrected to the dip.',
                $tank->name,
                Format::liters($dip->dip_reading),
                Format::liters($dip->book_stock),
                $variance > 0 ? 'Gain' : 'Loss',
                Format::liters(abs($variance)),
            );

        if ($request->expectsJson()) {
            return response()->json([
                'status' => $this->dipStatus($variance),
                'message' => $message,
                'dip' => $this->dipPayload($dip),
                'tank' => $this->levelPayload($tanks->levels()->firstWhere('tank.id', $tank->id)),
            ], 201);
        }

        return back()->with($matches ? 'success' : 'warning', $message);
    }

    public function transfer(TransferFuelRequest $request, Tank $tank, TankService $tanks): RedirectResponse
    {
        $to = Tank::findOrFail($request->integer('to_tank_id'));
        $reference = $tanks->transfer($tank, $to, (float) $request->input('quantity'), $request->user(), $request->input('notes'));

        return redirect()
            ->route('tanks.show', $tank)
            ->with('success', sprintf('%s moved from %s to %s (%s).', Format::liters($request->input('quantity')), $tank->name, $to->name, $reference))
            ->with('transferred', true);
    }

    /**
     * One tank card's live figures, shared by polling and the dip popup.
     *
     * @param  array<string, mixed>  $row  A row from TankService::levels()
     * @return array<string, mixed>
     */
    private function levelPayload(array $row): array
    {
        return [
            'id' => $row['tank']->id,
            'level' => $row['level'],
            'level_label' => Format::liters($row['level']),
            'percent' => $row['percent'],
            'ullage_label' => Format::liters($row['ullage']),
            'status' => $row['status'],
            'sold_today_label' => Format::liters($row['sold_today']),
            'received_today_label' => Format::liters($row['received_today']),
            'days_cover_label' => $row['days_cover'] !== null ? number_format($row['days_cover'], 1).' days' : '—',
            'last_dip' => $row['last_dip'] ? $this->dipPayload($row['last_dip']) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dipPayload(StockAdjustment $dip): array
    {
        $variance = (float) $dip->quantity;
        $book = (float) $dip->book_stock;

        return [
            'id' => $dip->id,
            'date_label' => $dip->business_date->format('d M'),
            'time_label' => $dip->created_at?->format('h:i A'),
            'measured_label' => Format::liters($dip->dip_reading),
            'book_label' => Format::liters($book),
            'variance' => $variance,
            'variance_label' => ($variance > 0 ? '+' : '').Format::liters($variance),
            'variance_percent' => $book > 0 ? round($variance / $book * 100, 2) : null,
            'status' => $this->dipStatus($variance),
            'by' => $dip->recorder?->name,
        ];
    }

    private function dipStatus(float $variance): string
    {
        return match (true) {
            abs($variance) < self::DIP_MATCH_TOLERANCE => 'match',
            $variance > 0 => 'gain',
            default => 'loss',
        };
    }
}
