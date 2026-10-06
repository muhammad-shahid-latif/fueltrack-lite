<?php

namespace App\Enums;

enum ProductUnit: string
{
    case Liter = 'L';
    case Bottle = 'bottle';
    case Can = 'can';
    case Piece = 'pcs';
    case Kilogram = 'kg';

    public function label(): string
    {
        return match ($this) {
            self::Liter => 'Liters (L)',
            self::Bottle => 'Bottles',
            self::Can => 'Cans',
            self::Piece => 'Pieces',
            self::Kilogram => 'Kilograms (kg)',
        };
    }
}
