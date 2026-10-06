<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDailyClosingRequest;
use App\Models\DailyClosing;
use App\Services\DailyClosingService;
use App\Support\BusinessClock;
use App\Support\Station;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DailyClosingController extends Controller
{
    public function index(): View
    {
        return view('closings.index', [
            'closings' => DailyClosing::query()
                ->with('closer')
                ->orderByDesc('business_date')
                ->paginate(20),
        ]);
    }

    public function create(Request $request, DailyClosingService $closings): View|RedirectResponse
    {
        $request->validate(['date' => ['nullable', 'date', 'before_or_equal:today']]);

        $date = $request->date('date') ?? BusinessClock::today();

        $existing = DailyClosing::query()->whereDate('business_date', $date)->first();

        if ($existing) {
            return redirect()->route('closings.show', $existing);
        }

        return view('closings.create', [
            'date' => $date,
            'summary' => $closings->summary($date),
            'defaultOpeningCash' => Station::defaultOpeningCash(),
        ]);
    }

    public function store(StoreDailyClosingRequest $request, DailyClosingService $closings): RedirectResponse
    {
        $closing = $closings->close(
            date: $request->date('business_date'),
            openingCash: (float) $request->input('opening_cash'),
            actualCash: (float) $request->input('actual_cash'),
            cardSales: (float) $request->input('card_sales'),
            creditSales: (float) $request->input('credit_sales'),
            closedBy: $request->user(),
            notes: $request->input('notes'),
        );

        return redirect()
            ->route('closings.show', $closing)
            ->with($closing->hasCashDifference() ? 'warning' : 'success', $closing->hasCashDifference()
                ? 'Day closed with a cash difference. Please review below.'
                : 'Day closed. Cash matches exactly.');
    }

    public function show(DailyClosing $closing): View
    {
        $closing->load('closer');

        return view('closings.show', ['closing' => $closing]);
    }
}
