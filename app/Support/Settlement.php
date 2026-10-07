<?php

namespace App\Support;

use App\Enums\BillStatus;
use Illuminate\Support\Carbon;

/**
 * Applies a pool of payments to bills oldest first (FIFO), the usual way
 * running accounts are settled. Gives every bill its paid amount, what is
 * left, and a status relative to its due date.
 */
final class Settlement
{
    /**
     * @template T of array{date: Carbon, due: Carbon, amount: float}
     *
     * @param  list<T>  $bills  oldest first
     * @return list<T&array{paid: float, remaining: float, status: BillStatus, days_overdue: int}>
     */
    public static function allocate(array $bills, float $paid, ?Carbon $today = null): array
    {
        $today = ($today ?? BusinessClock::today())->copy()->startOfDay();
        $pool = round($paid, 2);

        return array_map(function (array $bill) use (&$pool, $today) {
            $amount = round($bill['amount'], 2);
            $applied = round(min($amount, max(0, $pool)), 2);
            $pool = round($pool - $applied, 2);
            $remaining = round($amount - $applied, 2);

            $status = match (true) {
                $remaining <= 0.004 => BillStatus::Paid,
                $bill['due']->lt($today) => BillStatus::Overdue,
                $applied > 0 => BillStatus::Partial,
                default => BillStatus::Due,
            };

            return [
                ...$bill,
                'paid' => $applied,
                'remaining' => max(0.0, $remaining),
                'status' => $status,
                'days_overdue' => $status === BillStatus::Overdue ? (int) $bill['due']->diffInDays($today) : 0,
            ];
        }, $bills);
    }
}
