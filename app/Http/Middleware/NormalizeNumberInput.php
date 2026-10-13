<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\TransformsRequest;

/**
 * Accepts amounts typed with thousands separators: "1,000" or "25,000.50"
 * become "1000" and "25000.50" before validation. Only values shaped exactly
 * like a grouped number are touched, so text such as notes stays as typed.
 */
class NormalizeNumberInput extends TransformsRequest
{
    private const GROUPED_NUMBER = '/^\s*-?\d{1,3}(,\d{3})+(\.\d+)?\s*$/';

    protected function transform($key, $value)
    {
        return is_string($value) && preg_match(self::GROUPED_NUMBER, $value) === 1
            ? str_replace([',', ' '], '', $value)
            : $value;
    }
}
