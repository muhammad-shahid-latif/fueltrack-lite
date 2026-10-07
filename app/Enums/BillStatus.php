<?php

namespace App\Enums;

/**
 * Status of a supplier bill or a customer debt, worked out from payments
 * applied oldest-first. Never stored.
 */
enum BillStatus: string
{
    case Paid = 'paid';
    case Partial = 'partial';
    case Due = 'due';
    case Overdue = 'overdue';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Paid => 'text-bg-success',
            self::Partial => 'text-bg-info',
            self::Due => 'text-bg-warning',
            self::Overdue => 'text-bg-danger',
        };
    }
}
