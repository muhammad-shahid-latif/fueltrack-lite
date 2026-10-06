<?php

namespace App\Enums;

enum ProductType: string
{
    case Fuel = 'fuel';
    case Lubricant = 'lubricant';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Fuel => 'Fuel',
            self::Lubricant => 'Lubricant / Oil',
            self::Other => 'Other item',
        };
    }

    /**
     * Fuel is sold through nozzle meters; everything else through counter sales.
     */
    public function isMetered(): bool
    {
        return $this === self::Fuel;
    }
}
