<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecordDipRequest;
use App\Http\Requests\SaveTankRequest;
use App\Http\Requests\TransferFuelRequest;
use App\Models\FuelProduct;
use App\Models\Tank;
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
            'tanks' => $tanks->levels()->map(fn (array $row) => [
                'id' => $row['tank']->id,
                'level' => $row['level'],
                'level_label' => Format::liters($row['level']),
                'percent' => $row['percent'],
                'ullage_label' => Format::liters($row['ullage']),
                'status' => $row['status'],
                'sold_today_label' => Format::liters($row['sold_today']),
                'received_today_label' => Format::liters($row['received_today']),
            ])->values(),
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

        return view('tanks.show', [
            'tank' => $tank,
            'level' => $tanks->levels()->firstWhere('tank.id', $tank->id),
            'ledger' => $tanks->ledger($tank, $from, $to),
            'from' => $from,
            'to' => $to,
            'otherTanks' => Tank::query()->active()->where('fuel_product_id', $tank->fuel_product_id)->whereKeyNot($tank->id)->orderBy('name')->get(),
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

    public function dip(RecordDipRequest $request, Tank $tank, TankService $tanks): RedirectResponse
    {
        $dip = $tanks->recordDip($tank, (float) $request->input('dip_reading'), $request->user(), $request->input('notes'));
        $variance = (float) $dip->quantity;

        $message = abs($variance) < 0.005
            ? "Dip matches the book stock of {$tank->name} exactly."
            : sprintf('Dip saved. %s %s of %s; book stock corrected to %s.', $variance > 0 ? 'Gain' : 'Loss', Format::liters(abs($variance)), $tank->name, Format::liters($dip->dip_reading));

        return back()->with(abs($variance) < 0.005 ? 'success' : 'warning', $message);
    }

    public function transfer(TransferFuelRequest $request, Tank $tank, TankService $tanks): RedirectResponse
    {
        $to = Tank::findOrFail($request->integer('to_tank_id'));
        $reference = $tanks->transfer($tank, $to, (float) $request->input('quantity'), $request->user(), $request->input('notes'));

        return back()->with('success', sprintf('%s moved from %s to %s (%s).', Format::liters($request->input('quantity')), $tank->name, $to->name, $reference));
    }
}
