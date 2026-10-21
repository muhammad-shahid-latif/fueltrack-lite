<?php

namespace App\Support;

use App\Models\Setting;

/**
 * PM Fuel Relief Scheme setting: the relief one token gives (Rs. 500),
 * whatever the customer fills. Used to suggest the day's relief amount.
 */
final class ReliefScheme
{
    public const DEFAULT_PER_TOKEN = 500.0;

    public static function perToken(): float
    {
        return (float) Setting::get('relief_per_token', (string) self::DEFAULT_PER_TOKEN);
    }

    public static function save(float $perToken): void
    {
        Setting::putMany(['relief_per_token' => Format::input($perToken)]);
    }
}
