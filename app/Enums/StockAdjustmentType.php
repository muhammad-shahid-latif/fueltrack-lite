<?php

namespace App\Enums;

enum StockAdjustmentType: string
{
    case Dip = 'dip';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';

    public function label(): string
    {
        return match ($this) {
            self::Dip => 'Dip correction',
            self::TransferIn => 'Transfer in',
            self::TransferOut => 'Transfer out',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Dip => 'rulers',
            self::TransferIn => 'box-arrow-in-down',
            self::TransferOut => 'box-arrow-up',
        };
    }
}
