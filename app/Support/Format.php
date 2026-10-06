<?php

namespace App\Support;

use App\Enums\ProductUnit;

/**
 * Display formatting for money and fuel quantities.
 * Whole numbers drop their decimals: 226800.00 → "Rs. 226,800".
 */
final class Format
{
    public static function money(float|int|string|null $amount): string
    {
        $amount = (float) $amount;
        $sign = $amount < 0 ? '-' : '';

        return $sign.'Rs. '.self::number(abs($amount));
    }

    public static function liters(float|int|string|null $liters): string
    {
        return self::number((float) $liters).' L';
    }

    /**
     * Quantity with its unit: "810 L", "12 bottles", "1 can".
     */
    public static function quantity(float|int|string|null $quantity, ProductUnit|string|null $unit): string
    {
        $unit = $unit instanceof ProductUnit ? $unit : ProductUnit::tryFrom((string) $unit) ?? ProductUnit::Liter;
        $value = (float) $quantity;

        $suffix = match ($unit) {
            ProductUnit::Liter, ProductUnit::Kilogram, ProductUnit::Piece => $unit->value,
            default => $unit->value.(abs($value) === 1.0 ? '' : 's'),
        };

        return self::number($value).' '.$suffix;
    }

    public static function number(float|int|string|null $value): string
    {
        $value = round((float) $value, 2);

        return number_format($value, fmod($value, 1.0) === 0.0 ? 0 : 2);
    }

    public static function meter(float|int|string|null $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
