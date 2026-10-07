<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\SalaryAdvance;
use App\Models\SalarySlip;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Payroll over a range of months: every slip with its earnings and
 * deductions, the advances given, and the audit trail of payroll records.
 */
class PayrollReportService
{
    /**
     * @return array{from: Carbon, to: Carbon, slips: Collection<int, SalarySlip>, employees: Collection<int, array<string, mixed>>, months: Collection<int, array<string, mixed>>, advances: Collection<int, SalaryAdvance>, totals: array<string, float>, audit: Collection<int, ActivityLog>}
     */
    public function report(DateTimeInterface|string $fromMonth, DateTimeInterface|string $toMonth): array
    {
        $from = Carbon::parse($fromMonth)->startOfMonth();
        $to = Carbon::parse($toMonth)->startOfMonth();

        $slips = SalarySlip::query()
            ->with(['employee', 'payer'])
            ->whereDate('month', '>=', $from)
            ->whereDate('month', '<=', $to)
            ->orderBy('month')
            ->orderBy('employee_id')
            ->get();

        $advances = SalaryAdvance::query()
            ->with(['employee', 'recorder'])
            ->whereDate('business_date', '>=', $from)
            ->whereDate('business_date', '<=', $to->copy()->endOfMonth())
            ->orderByDesc('business_date')
            ->orderByDesc('id')
            ->get();

        $sum = fn (Collection $rows, string $column) => round((float) $rows->sum(fn (SalarySlip $slip) => (float) $slip->{$column}), 2);

        $employees = $slips->groupBy('employee_id')->map(fn (Collection $own) => [
            'employee' => $own->first()->employee,
            'months' => $own->count(),
            'basic' => $sum($own, 'basic_salary'),
            'bonus' => $sum($own, 'bonus'),
            'deductions' => round($sum($own, 'absence_deduction') + $sum($own, 'other_deduction') + $sum($own, 'advance_deduction'), 2),
            'net' => $sum($own, 'net_salary'),
            'paid' => round((float) $own->filter->isPaid()->sum(fn (SalarySlip $slip) => (float) $slip->net_salary), 2),
        ])->sortByDesc('net')->values();

        $months = $slips->groupBy(fn (SalarySlip $slip) => $slip->month->format('Y-m'))->map(fn (Collection $own) => [
            'month' => $own->first()->month,
            'staff' => $own->count(),
            'basic' => $sum($own, 'basic_salary'),
            'bonus' => $sum($own, 'bonus'),
            'deductions' => round($sum($own, 'absence_deduction') + $sum($own, 'other_deduction') + $sum($own, 'advance_deduction'), 2),
            'net' => $sum($own, 'net_salary'),
            'pending' => $own->reject->isPaid()->count(),
        ])->values();

        $audit = ActivityLog::query()
            ->with('user')
            ->forTypes(ActivityLog::PAYROLL)
            ->where('created_at', '>=', $from)
            ->where('created_at', '<', $to->copy()->addMonth())
            ->latest('id')
            ->limit(200)
            ->get();

        return [
            'from' => $from,
            'to' => $to,
            'slips' => $slips,
            'employees' => $employees,
            'months' => $months,
            'advances' => $advances,
            'totals' => [
                'basic' => $sum($slips, 'basic_salary'),
                'bonus' => $sum($slips, 'bonus'),
                'absence' => $sum($slips, 'absence_deduction'),
                'other' => $sum($slips, 'other_deduction'),
                'advance' => $sum($slips, 'advance_deduction'),
                'net' => $sum($slips, 'net_salary'),
                'paid' => round((float) $slips->filter->isPaid()->sum(fn (SalarySlip $slip) => (float) $slip->net_salary), 2),
                'advances_given' => round((float) $advances->sum('amount'), 2),
            ],
            'audit' => $audit,
        ];
    }
}
