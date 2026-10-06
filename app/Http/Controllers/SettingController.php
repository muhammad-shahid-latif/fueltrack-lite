<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingsRequest;
use App\Models\FuelProduct;
use App\Models\Setting;
use App\Support\Station;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function edit(): View
    {
        return view('settings.edit', [
            'settings' => [
                'station_name' => Station::name(),
                'station_address' => Station::address(),
                'station_phone' => Station::phone(),
                'default_opening_cash' => Station::defaultOpeningCash(),
            ],
            'products' => FuelProduct::query()->with('latestPrice')->orderByRaw('CASE WHEN type = ? THEN 0 ELSE 1 END', ['fuel'])->orderBy('id')->get(),
        ]);
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        Setting::putMany([
            'station_name' => $request->string('station_name')->trim()->toString(),
            'station_address' => $request->string('station_address')->trim()->toString(),
            'station_phone' => $request->input('station_phone'),
            'default_opening_cash' => (string) (float) $request->input('default_opening_cash'),
        ]);

        return redirect()->route('settings.edit')->with('success', 'Settings saved.');
    }
}
