<?php

namespace App\Http\Controllers;

use App\Models\Tank;
use App\Services\TankService;
use App\Support\BusinessClock;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Inventory movements across every tank: deliveries, shift sales, dips and transfers.
 */
class StockMovementController extends Controller
{
    public function __invoke(Request $request, TankService $tanks): View
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'tank' => ['nullable', 'integer', 'exists:tanks,id'],
            'type' => ['nullable', 'in:delivery,sales,dip,transfer_in,transfer_out'],
        ]);

        $from = $request->date('from') ?? BusinessClock::today()->subDays(2);
        $to = $request->date('to') ?? BusinessClock::today();
        $type = $request->input('type');

        $allTanks = Tank::query()->with('fuelProduct')->orderBy('name')->get();
        $selected = $request->filled('tank') ? $allTanks->where('id', $request->integer('tank')) : $allTanks;

        $ledgers = $selected->map(fn (Tank $tank) => ['tank' => $tank, ...$tanks->ledger($tank, $from, $to)])->values();

        $rows = $ledgers
            ->flatMap(fn (array $ledger) => $ledger['rows']->map(fn (array $row) => [...$row, 'tank' => $ledger['tank']]))
            ->when($type, fn ($rows) => $rows->where('type', $type))
            ->sortByDesc(fn (array $row) => $row['date']->toDateString().' '.$row['at']->format('H:i:s.u'))
            ->values();

        return view('stock.movements', [
            'ledgers' => $ledgers,
            'rows' => $rows,
            'allTanks' => $allTanks,
            'from' => $from,
            'to' => $to,
            'filters' => $request->only(['tank', 'type']),
        ]);
    }
}
