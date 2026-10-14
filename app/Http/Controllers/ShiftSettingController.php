<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateShiftPlanRequest;
use App\Support\BusinessClock;
use App\Support\ShiftSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Setup → Shifts: how many shifts the station runs and when each starts.
 */
class ShiftSettingController extends Controller
{
    public function edit(): View
    {
        return view('settings.shifts', [
            'plan' => ShiftSchedule::plan(),
            'shifts' => ShiftSchedule::shifts(),
            'current' => ShiftSchedule::at(BusinessClock::now()),
        ]);
    }

    public function update(UpdateShiftPlanRequest $request): RedirectResponse
    {
        $plan = $request->plan();
        ShiftSchedule::save($plan);

        $summary = collect(ShiftSchedule::shifts())->map(fn (array $row) => "{$row['name']} {$row['hours']}")->join(', ');

        return redirect()->route('shifts.edit')->with('success', sprintf('Shifts saved: %d %s (%s).', count($plan), str('shift')->plural(count($plan)), $summary));
    }
}
