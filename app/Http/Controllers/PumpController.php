<?php

namespace App\Http\Controllers;

use App\Http\Requests\SavePumpRequest;
use App\Models\FuelProduct;
use App\Models\Pump;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PumpController extends Controller
{
    public function index(): View
    {
        return view('pumps.index', [
            'pumps' => Pump::query()
                ->with(['nozzles' => fn ($query) => $query->with('fuelProduct')->withCount('meterReadings')])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('pumps.form', [
            'pump' => new Pump(['name' => $this->suggestedName(), 'is_active' => true]),
            'fuels' => collect(),
        ]);
    }

    public function store(SavePumpRequest $request): RedirectResponse
    {
        $pump = Pump::create([
            'name' => $request->string('name')->trim()->toString(),
            'is_active' => true,
        ]);

        return redirect()
            ->route('pumps.edit', $pump)
            ->with('success', "{$pump->name} added. Now add its nozzles.");
    }

    public function edit(Pump $pump): View
    {
        $pump->load(['nozzles' => fn ($query) => $query->with('fuelProduct')->withCount('meterReadings')]);

        return view('pumps.form', [
            'pump' => $pump,
            'fuels' => FuelProduct::query()->fuel()->active()->orderBy('id')->get(),
        ]);
    }

    public function update(SavePumpRequest $request, Pump $pump): RedirectResponse
    {
        $pump->update([
            'name' => $request->string('name')->trim()->toString(),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('pumps.edit', $pump)->with('success', "{$pump->name} saved.");
    }

    public function destroy(Pump $pump): RedirectResponse
    {
        $hasReadings = $pump->nozzles()->whereHas('meterReadings')->exists();

        if ($hasReadings) {
            return back()->with('danger', "{$pump->name} has meter readings on record, so it cannot be deleted. Mark it inactive instead.");
        }

        $pump->delete();

        return redirect()->route('pumps.index')->with('success', "{$pump->name} removed.");
    }

    private function suggestedName(): string
    {
        $next = Pump::query()->count() + 1;

        do {
            $name = sprintf('Pump %02d', $next++);
        } while (Pump::query()->where('name', $name)->exists());

        return $name;
    }
}
