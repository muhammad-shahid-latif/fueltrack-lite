<?php

namespace App\Enums;

enum SalarySlipStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
