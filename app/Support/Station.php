<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Station details from Settings, falling back to config/fueltrack.php.
 */
final class Station
{
    public static function name(): string
    {
        return Setting::get('station_name') ?: config('fueltrack.station.name');
    }

    public static function address(): string
    {
        return Setting::get('station_address') ?: config('fueltrack.station.city');
    }

    public static function phone(): ?string
    {
        return Setting::get('station_phone') ?: null;
    }

    public static function defaultOpeningCash(): float
    {
        return (float) Setting::get('default_opening_cash', '0');
    }
}
