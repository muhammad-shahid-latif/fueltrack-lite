<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Enums\SalarySlipStatus;
use App\Http\Requests\PaySalaryRequest;
use App\Http\Requests\UpdateSalarySlipRequest;
use App\Models\Employee;
use App\Models\SalarySlip;
use App\Services\DailyClosingService;
use App\Services\PayrollReportService;
use App\Services\PayrollService;
use App\Support\BusinessClock;
use App\Support\Format;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class PayrollController extends Controller
{
    public function index(Request $request, PayrollService $payroll, DailyClosingService $closings): View
    {
        $request->validate(['month' => ['nullable', 'date_format:Y-m']]);

        $current = BusinessClock::today()->startOfMonth();
        $month = $this->month($request->input('month')) ?? $current;

        $slips = SalarySlip::query()
            ->with(['employee', 'payer'])
            ->whereDate('month', $month)
            ->get()
            ->sortBy(fn (SalarySlip $slip) => $slip->employee->name)
            ->values();

        $missing = Employee::query()
            ->active()
            ->whereDate('joined_on', '<=', $month->copy()->endOfMonth())
            ->whereNotIn('id', $slips->pluck('employee_id'))
            ->count();

        $pending = $slips->where('status', SalarySlipStatus::Pending);

        return view('payroll.index', [
            'month' => $month,
            'previous' => $month->copy()->subMonthNoOverflow(),
            'next' => $month->lt($current) ? $month->copy()->addMonthNoOverflow() : null,
            'slips' => $slips,
            'missing' => $missing,
            'canPrepare' => $month->lte($current),
            'todayIsClosed' => $closings->isClosed(BusinessClock::today()),
            'cashBookedOn' => $payroll->bookingDate(PaymentMethod::Cash),
            'totals' => [
                'gross' => round($slips->sum(fn (SalarySlip $slip) => $slip->grossSalary()), 2),
                'advances' => round($slips->sum(fn (SalarySlip $slip) => (float) $slip->advance_deduction), 2),
                'net' => round($slips->sum(fn (SalarySlip $slip) => (float) $slip->net_salary), 2),
                'paid' => round($slips->where('status', SalarySlipStatus::Paid)->sum(fn (SalarySlip $slip) => (float) $slip->net_salary), 2),
                'pending' => round($pending->sum(fn (SalarySlip $slip) => (float) $slip->net_salary), 2),
                'pending_count' => $pending->count(),
            ],
            'outstanding' => $payroll->outstandingAdvances($slips->pluck('employee_id')),
            'paymentMethods' => [PaymentMethod::Cash, PaymentMethod::Bank],
        ]);
    }

    /**
     * Payroll report with every slip, advances given and the audit trail.
     */
    public function report(Request $request, PayrollReportService $reports): View
    {
        $request->validate([
            'from' => ['nullable', 'date_format:Y-m'],
            'to' => ['nullable', 'date_format:Y-m'],
        ]);

        $to = $this->month($request->input('to')) ?? BusinessClock::today()->startOfMonth();
        $from = $this->month($request->input('from')) ?? $to->copy()->subMonthsNoOverflow(5);

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        return view('payroll.report', ['report' => $reports->report($from, $to)]);
    }

    public function prepare(Request $request, PayrollService $payroll): RedirectResponse
    {
        $request->validate(['month' => ['required', 'date_format:Y-m']]);

        $month = $this->month($request->input('month'));
        $created = $payroll->prepare($month);

        return redirect()
            ->route('payroll.index', ['month' => $month->format('Y-m')])
            ->with($created->isEmpty() ? 'warning' : 'success', $created->isEmpty()
                ? 'Every active employee already has a salary slip for '.$month->format('F Y').'.'
                : sprintf('Salary sheet for %s prepared for %d %s.', $month->format('F Y'), $created->count(), str('employee')->plural($created->count())));
    }

    public function show(SalarySlip $slip): View
    {
        $slip->load(['employee', 'payer']);

        return view('payroll.slip', ['slip' => $slip]);
    }

    public function edit(SalarySlip $slip, PayrollService $payroll): View|RedirectResponse
    {
        if ($slip->isPaid()) {
            return redirect()->route('payroll.slips.show', $slip);
        }

        $slip->load('employee');

        return view('payroll.edit', [
            'slip' => $slip,
            'outstanding' => $payroll->outstandingAdvance($slip->employee),
        ]);
    }

    public function update(UpdateSalarySlipRequest $request, SalarySlip $slip, PayrollService $payroll): RedirectResponse
    {
        $slip = $payroll->update(
            slip: $slip,
            absentDays: (float) $request->input('absent_days'),
            bonus: (float) $request->input('bonus'),
            otherDeduction: (float) $request->input('other_deduction'),
            advanceDeduction: (float) $request->input('advance_deduction'),
            notes: $request->input('notes'),
        );

        return redirect()
            ->route('payroll.index', ['month' => $slip->month->format('Y-m')])
            ->with('success', "{$slip->employee->name}: net salary ".Format::money($slip->net_salary).'.');
    }

    public function destroy(SalarySlip $slip, PayrollService $payroll): RedirectResponse
    {
        $slip->load('employee');
        $payroll->delete($slip);

        return redirect()
            ->route('payroll.index', ['month' => $slip->month->format('Y-m')])
            ->with('success', "{$slip->employee->name} removed from the {$slip->month->format('F Y')} salary sheet.");
    }

    public function pay(PaySalaryRequest $request, SalarySlip $slip, PayrollService $payroll): RedirectResponse
    {
        $slip = $payroll->pay($slip, $request->enum('payment_method', PaymentMethod::class), $request->user());

        return redirect()
            ->route('payroll.index', ['month' => $slip->month->format('Y-m')])
            ->with('success', sprintf(
                '%s paid %s by %s%s.',
                $slip->employee->name,
                Format::money($slip->net_salary),
                strtolower($slip->payment_method->label()),
                $slip->paid_on->isToday() ? '' : ', booked on '.$slip->paid_on->format('d M Y').' because today is closed',
            ));
    }

    public function payAll(PaySalaryRequest $request, PayrollService $payroll): RedirectResponse
    {
        $month = $this->month($request->input('month')) ?? BusinessClock::today()->startOfMonth();
        $method = $request->enum('payment_method', PaymentMethod::class);
        $count = $payroll->payAll($month, $method, $request->user());
        $bookedOn = $payroll->bookingDate($method);

        return redirect()
            ->route('payroll.index', ['month' => $month->format('Y-m')])
            ->with('success', sprintf(
                '%d %s for %s paid%s.',
                $count,
                str('salary')->plural($count),
                $month->format('F Y'),
                $bookedOn->isToday() ? '' : ', booked on '.$bookedOn->format('d M Y').' because today is closed',
            ));
    }

    public function reverse(SalarySlip $slip, PayrollService $payroll): RedirectResponse
    {
        $slip = $payroll->reversePayment($slip);
        $slip->load('employee');

        return redirect()
            ->route('payroll.index', ['month' => $slip->month->format('Y-m')])
            ->with('success', "Payment to {$slip->employee->name} reversed. The salary is pending again.");
    }

    private function month(?string $value): ?Carbon
    {
        return $value ? Carbon::createFromFormat('!Y-m', $value)->startOfMonth() : null;
    }
}
