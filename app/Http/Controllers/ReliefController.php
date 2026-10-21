<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReliefSaleRequest;
use App\Http\Requests\UpdateReliefSettingsRequest;
use App\Models\FuelProduct;
use App\Models\ReliefSale;
use App\Services\DailyClosingService;
use App\Services\ReliefService;
use App\Support\BusinessClock;
use App\Support\Format;
use App\Support\ReliefScheme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * PM Fuel Relief Scheme. Tokens are handled in the government system; here
 * the station enters the day's relief total before closing, and tracks the
 * claim until the government pays it.
 */
class ReliefController extends Controller
{
    public function index(Request $request, ReliefService $relief, DailyClosingService $closings): View
    {
        $request->validate(['date' => ['nullable', 'date', 'before_or_equal:today']]);

        $date = $request->date('date') ?? BusinessClock::today();
        $saved = ReliefSale::query()->with('recorder')->whereDate('business_date', $date)->get()->keyBy('fuel_product_id');

        // The scheme is for petrol: show petrol fuels, plus any fuel that already has relief.
        $fuels = FuelProduct::query()->fuel()->orderBy('name')->get();
        $isPetrol = fn (FuelProduct $product) => $product->code === 'petrol' || str_contains(strtolower($product->name), 'petrol');
        $shown = $fuels->contains($isPetrol) ? $fuels->filter($isPetrol) : $fuels;

        $rows = $shown->merge($fuels->filter(fn (FuelProduct $product) => $saved->has($product->id)))
            ->unique('id')
            ->filter(fn (FuelProduct $product) => $product->is_active || $saved->has($product->id))
            ->map(fn (FuelProduct $product) => (object) [
                'product' => $product,
                'meterLiters' => ReliefService::meterLitersOn($date, $product),
                'meterAmount' => ReliefService::meterAmountOn($date, $product),
                'entry' => $saved->get($product->id),
            ])
            ->values();

        return view('relief.index', [
            'date' => $date,
            'isOpen' => ! $closings->isClosed($date),
            'rows' => $rows,
            'totals' => ReliefService::totalsOn($date->toDateString()),
            'pending' => ReliefService::pendingTotal(),
            'received' => round((float) ReliefSale::query()->whereNotNull('received_on')->sum('relief_amount'), 2),
            'days' => $relief->claimDays(),
            'perToken' => ReliefScheme::perToken(),
            'returnTo' => $request->query('return') === 'closing' ? 'closing' : null,
        ]);
    }

    public function store(StoreReliefSaleRequest $request, ReliefService $relief): RedirectResponse
    {
        $date = $request->date('business_date');

        $entry = $relief->saveDay(
            date: $date,
            product: FuelProduct::findOrFail($request->integer('fuel_product_id')),
            tokens: $request->integer('tokens'),
            amount: (float) $request->input('amount'),
            recordedBy: $request->user(),
            notes: $request->input('notes'),
        );

        $message = sprintf(
            'Relief saved for %s: %d %s, %s (%s %s). Taken off the cash at closing.',
            $date->format('d M'),
            $entry->tokens,
            str('token')->plural($entry->tokens),
            Format::money($entry->relief_amount),
            Format::liters($entry->quantity),
            $entry->fuelProduct->name,
        );

        return ($request->input('return') === 'closing'
            ? redirect()->route('closings.create', ['date' => $date->toDateString()])
            : redirect()->route('relief.index', ['date' => $date->toDateString()]))
            ->with('success', $message);
    }

    public function destroy(ReliefSale $sale, ReliefService $relief): RedirectResponse
    {
        $relief->delete($sale);

        return redirect()
            ->route('relief.index', ['date' => $sale->business_date->toDateString()])
            ->with('success', 'Relief removed for '.$sale->business_date->format('d M Y').'.');
    }

    public function receive(Request $request, string $date, ReliefService $relief): RedirectResponse
    {
        $validated = $request->validate([
            'received_on' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.$date],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        $amount = $relief->markReceived($date, $validated['received_on'], $validated['reference'] ?? null);

        return back()->with('success', sprintf('%s received from the government for %s.', Format::money($amount), Carbon::parse($date)->format('d M Y')));
    }

    public function undoReceive(string $date, ReliefService $relief): RedirectResponse
    {
        $relief->undoReceived($date);

        return back()->with('success', 'Marked as not received. The relief is pending again.');
    }

    public function settings(UpdateReliefSettingsRequest $request): RedirectResponse
    {
        ReliefScheme::save((float) $request->input('per_token'));

        return redirect()->route('relief.index')->with('success', 'Relief per token saved.');
    }
}
