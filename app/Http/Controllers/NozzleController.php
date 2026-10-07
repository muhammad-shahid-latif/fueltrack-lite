<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveNozzleRequest;
use App\Models\Nozzle;
use App\Models\Pump;
use Illuminate\Http\RedirectResponse;

class NozzleController extends Controller
{
    public function store(SaveNozzleRequest $request, Pump $pump): RedirectResponse
    {
        $nozzle = $pump->nozzles()->create([
            'number' => (int) $pump->nozzles()->max('number') + 1,
            'fuel_product_id' => $request->integer('fuel_product_id'),
            'tank_id' => $request->integer('tank_id') ?: null,
            'current_meter' => $request->input('opening_meter'),
            'is_active' => true,
        ]);

        return redirect()->route('pumps.edit', $pump)->with('success', "Nozzle {$nozzle->number} added to {$pump->name}.");
    }

    public function update(SaveNozzleRequest $request, Pump $pump, Nozzle $nozzle): RedirectResponse
    {
        abort_unless($nozzle->pump_id === $pump->id, 404);

        $nozzle->update([
            'fuel_product_id' => $request->integer('fuel_product_id'),
            'tank_id' => $request->integer('tank_id') ?: null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('pumps.edit', $pump)->with('success', "Nozzle {$nozzle->number} saved.");
    }

    public function destroy(Pump $pump, Nozzle $nozzle): RedirectResponse
    {
        abort_unless($nozzle->pump_id === $pump->id, 404);

        if ($nozzle->meterReadings()->exists()) {
            return back()->with('danger', "Nozzle {$nozzle->number} has meter readings on record, so it cannot be deleted. Mark it inactive instead.");
        }

        $nozzle->delete();

        return redirect()->route('pumps.edit', $pump)->with('success', "Nozzle {$nozzle->number} removed.");
    }
}
