<?php

namespace App\Enums;

use App\Support\ShiftSchedule;
use DateTimeInterface;

/**
 * Storage slots for shifts. Names, hours and how many are in use come from
 * the owner's plan (ShiftSchedule), so a station can run 1, 2 or 3 shifts.
 */
enum Shift: string
{
    case Morning = 'morning';
    case Evening = 'evening';
    case Night = 'night';

    public function label(): string
    {
        return ShiftSchedule::label($this);
    }

    public function hours(): string
    {
        return ShiftSchedule::hours($this);
    }

    public static function at(DateTimeInterface $time): self
    {
        return ShiftSchedule::at($time);
    }
}
