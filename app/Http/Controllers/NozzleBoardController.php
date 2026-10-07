<?php

namespace App\Http\Controllers;

use App\Services\NozzleReportService;
use App\Support\BusinessClock;
use App\Support\Format;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Live nozzle operations: current meters and today's sales per nozzle.
 */
class NozzleBoardController extends Controller
{
    public function index(NozzleReportService $nozzles): View
    {
        return view('nozzles.index', [
            'board' => $nozzles->live(),
            'businessDate' => BusinessClock::today(),
        ]);
    }

    public function live(NozzleReportService $nozzles): JsonResponse
    {
        return response()->json([
            'at' => BusinessClock::now()->format('h:i:s A'),
            'nozzles' => $nozzles->live()->map(fn (array $row) => [
                'id' => $row['nozzle']->id,
                'meter' => Format::meter($row['nozzle']->current_meter),
                'liters' => Format::liters($row['liters']),
                'amount' => Format::money($row['amount']),
                'readings' => $row['readings'],
                'shifts' => $row['shifts'],
                'share' => $row['share'],
                'last' => $row['last_at']?->format('h:i A'),
            ])->values(),
        ]);
    }

    public function performance(Request $request, NozzleReportService $nozzles): View
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from', 'before_or_equal:today'],
        ]);

        $from = $request->date('from') ?? BusinessClock::today()->subDays(6);
        $to = $request->date('to') ?? BusinessClock::today();

        if ($from->diffInDays($to) > 62) {
            $from = $to->copy()->subDays(62);
        }

        return view('reports.nozzles', [
            'report' => $nozzles->performance($from, $to),
            'from' => $from,
            'to' => $to,
        ]);
    }
}
