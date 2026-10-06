<?php

namespace App\Enums;

use DateTimeInterface;
use Illuminate\Support\Carbon;

enum Shift: string
{
    case Morning = 'morning';
    case Evening = 'evening';
    case Night = 'night';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function hours(): string
    {
        return match ($this) {
            self::Morning => '6 AM – 2 PM',
            self::Evening => '2 PM – 10 PM',
            self::Night => '10 PM – 6 AM',
        };
    }

    public static function at(DateTimeInterface $time): self
    {
        $hour = Carbon::instance($time)->hour;

        return match (true) {
            $hour >= 6 && $hour < 14 => self::Morning,
            $hour >= 14 && $hour < 22 => self::Evening,
            default => self::Night,
        };
    }
}
