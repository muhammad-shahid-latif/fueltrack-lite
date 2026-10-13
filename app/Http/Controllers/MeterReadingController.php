<?php

namespace App\Http\Controllers;

use App\Enums\Shift;
use App\Http\Requests\StoreMeterReadingRequest;
use App\Http\Requests\UpdateMeterReadingRequest;
use App\Models\DailyClosing;
use App\Models\MeterReading;
use App\Models\Nozzle;
use App\Services\DailyClosingService;
use App\Services\MeterReadingService;
use App\Support\BusinessClock;
use App\Support\Format;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class MeterReadingController extends Controller
{
    public function index(Request $request, DailyClosingService $closings): View
    {
        $request->validate([
            'date' => ['nullable', 'date'],
            'shift' => ['nullable', 'in:'.implode(',', array_column(Shift::cases(), 'value'))],
        ]);

        $date = $this->dateFrom($request);
        $shift = $request->enum('shift', Shift::class);

        $readings = MeterReading::query()
            ->with(['nozzle.pump', 'nozzle.fuelProduct', 'sale', 'recorder'])
            ->whereDate('business_date', $date)
            ->when($shift, fn (Builder $query, Shift $shift) => $query->where('shift', $shift->value))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('meter-readings.index', [
            'date' => $date,
            'shift' => $shift,
            'shifts' => Shift::cases(),
            'readings' => $readings,
            'summary' => $closings->summary($date),
            'closing' => DailyClosing::query()->whereDate('business_date', $date)->first(),
        ]);
    }

    public function create(Request $request, DailyClosingService $closings): View
    {
        $nozzles = Nozzle::inDisplayOrder(Nozzle::query()
            ->with(['pump', 'fuelProduct'])
            ->where('is_active', true)
            ->whereHas('pump', fn (Builder $query) => $query->where('is_active', true))
            ->whereHas('fuelProduct', fn (Builder $query) => $query->fuel()->active())
            ->get());

        return view('meter-readings.create', [
            'nozzles' => $nozzles->map(fn (Nozzle $nozzle) => [
                'id' => $nozzle->id,
                'label' => $nozzle->label(),
                'fuel' => $nozzle->fuelProduct->name,
                'rate' => (float) $nozzle->fuelProduct->selling_price,
                'current_meter' => Format::meter($nozzle->current_meter),
            ])->values(),
            'selectedNozzleId' => $request->integer('nozzle') ?: null,
            'shifts' => Shift::cases(),
            'currentShift' => Shift::at(BusinessClock::now()),
            'businessDate' => BusinessClock::today(),
            'todayIsClosed' => $closings->isClosed(BusinessClock::today()),
        ]);
    }

    public function store(StoreMeterReadingRequest $request, MeterReadingService $service): RedirectResponse
    {
        $reading = $service->record(
            nozzle: Nozzle::findOrFail($request->integer('nozzle_id')),
            date: BusinessClock::today(),
            shift: $request->enum('shift', Shift::class),
            openingMeter: (float) $request->input('opening_meter'),
            closingMeter: (float) $request->input('closing_meter'),
            recordedBy: $request->user(),
            notes: $request->input('notes'),
        );

        $sale = $reading->sale;

        return redirect()
            ->route('meter-readings.index')
            ->with('success', sprintf(
                '%s shift reading saved: %s × %s = %s',
                $reading->shift->label(),
                Format::liters($sale->quantity),
                Format::money($sale->rate),
                Format::money($sale->amount),
            ));
    }

    public function edit(MeterReading $meterReading, DailyClosingService $closings, MeterReadingService $service): View|RedirectResponse
    {
        if ($closings->isClosed($meterReading->business_date)) {
            return $this->closedDay($meterReading);
        }

        $meterReading->load(['nozzle.pump', 'nozzle.fuelProduct', 'sale', 'recorder']);

        return view('meter-readings.edit', [
            'reading' => $meterReading,
            'previous' => $service->neighbour($meterReading, before: true),
            'next' => $service->neighbour($meterReading, before: false),
            'shifts' => Shift::cases(),
        ]);
    }

    public function update(UpdateMeterReadingRequest $request, MeterReading $meterReading, MeterReadingService $service): RedirectResponse
    {
        $reading = $service->update(
            reading: $meterReading,
            shift: $request->enum('shift', Shift::class),
            openingMeter: (float) $request->input('opening_meter'),
            closingMeter: (float) $request->input('closing_meter'),
            notes: $request->input('notes'),
        );

        return redirect()
            ->route('meter-readings.index', ['date' => $reading->business_date->toDateString()])
            ->with('success', sprintf(
                'Reading updated: %s × %s = %s',
                Format::liters($reading->sale->quantity),
                Format::money($reading->sale->rate),
                Format::money($reading->sale->amount),
            ));
    }

    public function destroy(MeterReading $meterReading, MeterReadingService $service): RedirectResponse
    {
        $meterReading->load('nozzle.pump');
        $service->delete($meterReading);

        return redirect()
            ->route('meter-readings.index', ['date' => $meterReading->business_date->toDateString()])
            ->with('success', sprintf(
                'Reading deleted. %s is back to %s.',
                $meterReading->nozzle->label(),
                Format::meter($meterReading->opening_meter),
            ));
    }

    private function closedDay(MeterReading $reading): RedirectResponse
    {
        return redirect()
            ->route('meter-readings.index', ['date' => $reading->business_date->toDateString()])
            ->with('warning', sprintf('%s is closed. Reopen it from Daily closing to make changes.', $reading->business_date->format('d M Y')));
    }

    private function dateFrom(Request $request): Carbon
    {
        return $request->date('date') ?? BusinessClock::today();
    }
}
