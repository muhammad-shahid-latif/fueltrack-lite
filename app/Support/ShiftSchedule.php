<?php

namespace App\Support;

use App\Enums\Shift;
use App\Models\Setting;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Once;

/**
 * The station's shift plan, set by the owner in Setup → Shifts.
 *
 * A station runs 1, 2 or 3 shifts. They are stored in the fixed Shift slots
 * (morning, evening, night) so saved readings never change meaning; only the
 * names and hours do. Each shift runs from its start until the next shift's
 * start, so the day is always covered with no gaps or overlaps.
 */
final class ShiftSchedule
{
    public const SETTING_KEY = 'shift_plan';

    public const MAX_SHIFTS = 3;

    /** Shortest allowed shift, in minutes. */
    public const MIN_MINUTES = 60;

    /** @var list<array{name: string, start: string}> */
    public const DEFAULT_PLAN = [
        ['name' => 'Morning', 'start' => '06:00'],
        ['name' => 'Evening', 'start' => '14:00'],
        ['name' => 'Night', 'start' => '22:00'],
    ];

    /**
     * The saved plan as [{name, start}], one entry per running shift.
     *
     * @return list<array{name: string, start: string}>
     */
    public static function plan(): array
    {
        // Memoized per request: labels are read once per table row.
        return once(function () {
            $saved = json_decode((string) Setting::get(self::SETTING_KEY), true);

            return is_array($saved) && self::problem($saved) === null ? array_values($saved) : self::DEFAULT_PLAN;
        });
    }

    /**
     * Running shifts with their times.
     *
     * @return list<array{shift: Shift, name: string, start: string, end: string, minutes: int, hours: string}>
     */
    public static function shifts(): array
    {
        $plan = self::plan();
        $slots = Shift::cases();
        $count = count($plan);

        return array_map(function (int $i) use ($plan, $slots, $count) {
            $start = $plan[$i]['start'];
            $end = $plan[($i + 1) % $count]['start'];

            return [
                'shift' => $slots[$i],
                'name' => $plan[$i]['name'],
                'start' => $start,
                'end' => $end,
                'minutes' => $count === 1 ? 1440 : self::span($start, $end),
                'hours' => self::clock($start).' – '.self::clock($end),
            ];
        }, array_keys($plan));
    }

    /**
     * Shifts a new reading can be entered for.
     *
     * @return list<Shift>
     */
    public static function active(): array
    {
        return array_column(self::shifts(), 'shift');
    }

    /** @return list<string> */
    public static function activeValues(): array
    {
        return array_map(fn (Shift $shift) => $shift->value, self::active());
    }

    /**
     * Shown name. A slot no longer in use (after going from 3 to 2 shifts)
     * keeps its standard name so its old readings still read well.
     */
    public static function label(Shift $shift): string
    {
        foreach (self::shifts() as $row) {
            if ($row['shift'] === $shift) {
                return $row['name'];
            }
        }

        return ucfirst($shift->value);
    }

    public static function hours(Shift $shift): string
    {
        foreach (self::shifts() as $row) {
            if ($row['shift'] === $shift) {
                return $row['hours'];
            }
        }

        return '';
    }

    /** The shift running at a moment, used to pre-select it on the reading form. */
    public static function at(DateTimeInterface $time): Shift
    {
        $minute = Carbon::instance($time)->hour * 60 + Carbon::instance($time)->minute;

        foreach (self::shifts() as $row) {
            $start = self::minutes($row['start']);
            $offset = ($minute - $start + 1440) % 1440;

            if ($offset < $row['minutes']) {
                return $row['shift'];
            }
        }

        return self::active()[0];
    }

    /**
     * @param  list<array{name: string, start: string}>  $plan
     */
    public static function save(array $plan): void
    {
        Setting::putMany([self::SETTING_KEY => json_encode(array_values($plan))]);
        Once::flush();
    }

    /**
     * Why a plan cannot be used, or null when it is fine.
     *
     * @param  array<int, mixed>  $plan
     */
    public static function problem(array $plan): ?string
    {
        $count = count($plan);

        if ($count < 1 || $count > self::MAX_SHIFTS) {
            return 'Choose 1, 2 or 3 shifts.';
        }

        foreach (array_values($plan) as $row) {
            if (! is_array($row) || trim((string) ($row['name'] ?? '')) === '' || ! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) ($row['start'] ?? ''))) {
                return 'Every shift needs a name and a start time.';
            }
        }

        $names = array_map(fn (array $row) => mb_strtolower(trim($row['name'])), $plan);

        if (count(array_unique($names)) !== $count) {
            return 'Each shift needs a different name.';
        }

        if ($count === 1) {
            return null;
        }

        $plan = array_values($plan);
        $total = 0;

        foreach ($plan as $i => $row) {
            $span = self::span($row['start'], $plan[($i + 1) % $count]['start']);

            if ($span < self::MIN_MINUTES) {
                return 'Each shift must be at least 1 hour, and the start times must follow each other through the day.';
            }

            $total += $span;
        }

        // Start times out of order wrap around the clock more than once.
        return $total === 1440 ? null : 'Start times must follow each other through the day (e.g. 06:00, 14:00, 22:00).';
    }

    /** Minutes from one clock time to the next occurrence of another. */
    private static function span(string $from, string $to): int
    {
        return (self::minutes($to) - self::minutes($from) + 1440) % 1440;
    }

    private static function minutes(string $time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return $hour * 60 + $minute;
    }

    private static function clock(string $time): string
    {
        return Carbon::createFromFormat('H:i', $time)->format('g:i A');
    }
}
