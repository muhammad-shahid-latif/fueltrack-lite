<?php

namespace App\Enums;

enum ExpenseCategory: string
{
    case Electricity = 'electricity';
    case Generator = 'generator';
    case Cleaning = 'cleaning';
    case Maintenance = 'maintenance';
    case Security = 'security';
    case Office = 'office';
    case Other = 'other';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
