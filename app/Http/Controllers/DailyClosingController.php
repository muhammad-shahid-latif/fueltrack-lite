<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDailyClosingRequest;
use App\Http\Requests\UpdateDailyClosingRequest;
use App\Jobs\SendClosingSummary;
use App\Models\DailyClosing;
use App\Services\DailyClosingService;
use App\Support\BusinessClock;
use App\Support\Station;
use App\Support\WhatsAppSettings;
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
            closedBy: $request->user(),
            notes: $request->input('notes'),
        );

        // WhatsApp summary to the owner, sent once the page has been answered.
        if (WhatsAppSettings::ready()) {
            SendClosingSummary::dispatchAfterResponse($closing->id);
        }

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

    public function edit(DailyClosing $closing, DailyClosingService $closings): View
    {
        return view('closings.edit', [
            'closing' => $closing,
            'summary' => $closings->summary($closing->business_date),
        ]);
    }

    public function update(UpdateDailyClosingRequest $request, DailyClosing $closing, DailyClosingService $closings): RedirectResponse
    {
        $closing = $closings->updateCash(
            closing: $closing,
            openingCash: (float) $request->input('opening_cash'),
            actualCash: (float) $request->input('actual_cash'),
            cardSales: (float) $request->input('card_sales'),
            notes: $request->input('notes'),
        );

        // WhatsApp summary to the owner, sent once the page has been answered.
        if (WhatsAppSettings::ready()) {
            SendClosingSummary::dispatchAfterResponse($closing->id);
        }

        return redirect()
            ->route('closings.show', $closing)
            ->with($closing->hasCashDifference() ? 'warning' : 'success', $closing->hasCashDifference()
                ? 'Cash count updated. There is still a cash difference.'
                : 'Cash count updated. Cash matches exactly.');
    }

    public function destroy(DailyClosing $closing, DailyClosingService $closings): RedirectResponse
    {
        $closings->reopen($closing);

        return redirect()
            ->route('meter-readings.index', ['date' => $closing->business_date->toDateString()])
            ->with('success', sprintf(
                '%s is open again. Correct its records, then close the day from Daily closing.',
                $closing->business_date->format('d M Y'),
            ));
    }
}
