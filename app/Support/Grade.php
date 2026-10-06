<?php

namespace App\Support;

use App\Enums\ProductType;
use App\Models\FuelProduct;

/**
 * The colour identity of a product, used for dots, badges and nozzle tiles.
 */
final class Grade
{
    public static function cssClass(FuelProduct $product): string
    {
        return match (true) {
            $product->code === 'petrol' => 'grade-petrol',
            $product->code === 'diesel' => 'grade-diesel',
            $product->code === 'hi-octane' => 'grade-hioctane',
            $product->type === ProductType::Lubricant => 'grade-lube',
            default => 'grade-other',
        };
    }
}
