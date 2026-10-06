<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function daily(Request $request, ReportService $reports): View
    {
        $request->validate([
            'from' => ['nullable', 'date', 'before_or_equal:today'],
            'to' => ['nullable', 'date', 'after_or_equal:from', 'before_or_equal:today'],
        ]);

        $from = $request->date('from') ?? today();
        $to = $request->date('to') ?? ($request->filled('from') ? today() : $from->copy());

        // Keep the per-day table readable and the queries bounded.
        if ($from->diffInDays($to) > 92) {
            $from = $to->copy()->subDays(92);
        }

        return view('reports.daily', ['report' => $reports->dailyReport($from, $to)]);
    }
}
