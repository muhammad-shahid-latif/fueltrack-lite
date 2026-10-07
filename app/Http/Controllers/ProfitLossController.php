<?php

namespace App\Http\Controllers;

use App\Services\ProfitLossService;
use App\Support\BusinessClock;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfitLossController extends Controller
{
    public function __invoke(Request $request, ProfitLossService $profitLoss): View
    {
        $request->validate([
            'from' => ['nullable', 'date', 'before_or_equal:today'],
            'to' => ['nullable', 'date', 'after_or_equal:from', 'before_or_equal:today'],
        ]);

        $today = BusinessClock::today();
        $from = $request->date('from') ?? $today->copy()->startOfMonth();
        $to = $request->date('to') ?? $today->copy();

        // Bounded so the per-product queries stay fast.
        if ($from->diffInDays($to) > 366) {
            $from = $to->copy()->subDays(366);
        }

        $lastMonth = $today->copy()->subMonthNoOverflow();

        return view('reports.profit-loss', [
            'report' => $profitLoss->report($from, $to),
            'monthly' => $profitLoss->monthly(6),
            'presets' => [
                'This month' => [$today->copy()->startOfMonth(), $today],
                'Last month' => [$lastMonth->copy()->startOfMonth(), $lastMonth->copy()->endOfMonth()],
                'Last 3 months' => [$today->copy()->subMonthsNoOverflow(2)->startOfMonth(), $today],
                'This year' => [$today->copy()->startOfYear(), $today],
            ],
        ]);
    }
}
