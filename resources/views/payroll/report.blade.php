@php($t = $report['totals'])
<x-layouts.app title="Payroll report" :subtitle="$report['from']->format('F Y').' to '.$report['to']->format('F Y')">
    <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4 d-print-none">
        <form method="GET" class="d-flex flex-wrap align-items-end gap-2">
            <div>
                <label for="from" class="form-label small mb-1">From month</label>
                <input type="month" id="from" name="from" value="{{ $report['from']->format('Y-m') }}" class="form-control">
            </div>
            <div>
                <label for="to" class="form-label small mb-1">To month</label>
                <input type="month" id="to" name="to" value="{{ $report['to']->format('Y-m') }}" max="{{ today()->format('Y-m') }}" class="form-control">
            </div>
            <button type="submit" class="btn btn-dark"><i class="bi bi-funnel me-1"></i> Show</button>
        </form>
        <div class="d-flex gap-2">
            <a href="{{ route('payroll.index') }}" class="btn btn-light"><i class="bi bi-cash-coin me-1"></i> Salary sheet</a>
            <button type="button" class="btn btn-warning" onclick="window.print()"><i class="bi bi-printer me-1"></i> Print / PDF</button>
        </div>
    </div>

    <section class="hero-3d mb-4" data-tilt="1">
        <div class="row g-3">
            <div class="col-lg-3"><div class="hero-label">Net salaries</div><div class="hero-value">@money(round($t['net']))</div><div class="hero-sub">{{ $report['slips']->count() }} slips · {{ $report['employees']->count() }} staff</div></div>
            <div class="col-6 col-lg-2"><div class="hero-tile"><div class="hero-label">Basic</div><div class="hero-value is-white" style="font-size: 1.25rem;">@money(round($t['basic']))</div></div></div>
            <div class="col-6 col-lg-2"><div class="hero-tile"><div class="hero-label">Bonuses</div><div class="hero-value is-white" style="font-size: 1.25rem;">+@money(round($t['bonus']))</div></div></div>
            <div class="col-6 col-lg-2"><div class="hero-tile"><div class="hero-label">Deductions</div><div class="hero-value is-white" style="font-size: 1.25rem;">−@money(round($t['absence'] + $t['other'] + $t['advance']))</div></div></div>
            <div class="col-6 col-lg-3"><div class="hero-tile"><div class="hero-label">Paid · advances given</div><div class="hero-value is-white" style="font-size: 1.25rem;">@money(round($t['paid'])) · @money(round($t['advances_given']))</div></div></div>
        </div>
    </section>

    <div class="row g-4 mb-4">
        <div class="col-xl-5">
            <div class="card h-100">
                <div class="card-header">By month</div>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead><tr><th>Month</th><th class="text-end">Staff</th><th class="text-end">Bonus</th><th class="text-end">Deductions</th><th class="text-end">Net</th><th>Status</th></tr></thead>
                        <tbody>
                            @forelse ($report['months'] as $row)
                                <tr>
                                    <td><a href="{{ route('payroll.index', ['month' => $row['month']->format('Y-m')]) }}" class="fw-semibold text-decoration-none">{{ $row['month']->format('M Y') }}</a></td>
                                    <td class="text-end">{{ $row['staff'] }}</td>
                                    <td class="text-end ft-num amount-in">@money($row['bonus'])</td>
                                    <td class="text-end ft-num amount-out">@money($row['deductions'])</td>
                                    <td class="text-end ft-num fw-bold">@money($row['net'])</td>
                                    <td>@if ($row['pending'] > 0)<span class="badge text-bg-warning">{{ $row['pending'] }} pending</span>@else<span class="badge text-bg-success">Paid</span>@endif</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="empty-state"><i class="bi bi-calendar3"></i>No salary slips in this range.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xl-7">
            <div class="card h-100">
                <div class="card-header">By employee</div>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead><tr><th>Employee</th><th class="text-end">Months</th><th class="text-end">Basic</th><th class="text-end">Bonus</th><th class="text-end">Deductions</th><th class="text-end">Net</th></tr></thead>
                        <tbody>
                            @foreach ($report['employees'] as $row)
                                <tr>
                                    <td><a href="{{ route('employees.show', $row['employee']) }}" class="d-flex align-items-center gap-2 text-decoration-none text-reset"><x-employee-avatar :employee="$row['employee']" size="sm" /><span><span class="fw-semibold d-block">{{ $row['employee']->name }}</span><span class="small text-secondary">{{ $row['employee']->designation }}</span></span></a></td>
                                    <td class="text-end">{{ $row['months'] }}</td>
                                    <td class="text-end ft-num">@money($row['basic'])</td>
                                    <td class="text-end ft-num amount-in">@money($row['bonus'])</td>
                                    <td class="text-end ft-num amount-out">@money($row['deductions'])</td>
                                    <td class="text-end ft-num fw-bold">@money($row['net'])</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4 report-sheet">
        <div class="card-header">Salary transactions</div>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr><th>Month</th><th>Employee</th><th class="text-end">Basic</th><th class="text-end">Bonus</th><th class="text-end">Absence</th><th class="text-end">Advance</th><th class="text-end">Other</th><th class="text-end">Net</th><th>Paid</th></tr>
                </thead>
                <tbody>
                    @forelse ($report['slips'] as $slip)
                        <tr>
                            <td class="text-nowrap">{{ $slip->month->format('M Y') }}</td>
                            <td><a href="{{ route('payroll.slips.show', $slip) }}" class="text-decoration-none fw-semibold">{{ $slip->employee->name }}</a>@if ($slip->notes)<div class="small text-secondary">{{ $slip->notes }}</div>@endif</td>
                            <td class="text-end ft-num">@money($slip->basic_salary)</td>
                            <td class="text-end ft-num amount-in">{{ (float) $slip->bonus > 0 ? '+'.\App\Support\Format::money($slip->bonus) : '—' }}</td>
                            <td class="text-end ft-num amount-out">{{ (float) $slip->absence_deduction > 0 ? '−'.\App\Support\Format::money($slip->absence_deduction) : '—' }}</td>
                            <td class="text-end ft-num amount-out">{{ (float) $slip->advance_deduction > 0 ? '−'.\App\Support\Format::money($slip->advance_deduction) : '—' }}</td>
                            <td class="text-end ft-num amount-out">{{ (float) $slip->other_deduction > 0 ? '−'.\App\Support\Format::money($slip->other_deduction) : '—' }}</td>
                            <td class="text-end ft-num fw-bold">@money($slip->net_salary)</td>
                            <td class="small text-nowrap">
                                @if ($slip->isPaid())
                                    <span class="badge text-bg-success">Paid</span> {{ $slip->paid_on?->format('d M') }} · {{ $slip->payment_method?->label() }}
                                    <div class="text-secondary">by {{ $slip->payer?->name ?? '—' }}</div>
                                @else
                                    <span class="badge text-bg-warning">Pending</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="empty-state"><i class="bi bi-receipt"></i>No slips.</td></tr>
                    @endforelse
                </tbody>
                @if ($report['slips']->isNotEmpty())
                    <tfoot>
                        <tr>
                            <td colspan="2">Total</td>
                            <td class="text-end ft-num">@money($t['basic'])</td>
                            <td class="text-end ft-num">@money($t['bonus'])</td>
                            <td class="text-end ft-num">@money($t['absence'])</td>
                            <td class="text-end ft-num">@money($t['advance'])</td>
                            <td class="text-end ft-num">@money($t['other'])</td>
                            <td class="text-end ft-num">@money($t['net'])</td>
                            <td></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-5">
            <div class="card h-100">
                <div class="card-header">Advances given</div>
                <ul class="list-group list-group-flush">
                    @forelse ($report['advances'] as $advance)
                        <li class="list-group-item d-flex justify-content-between align-items-center px-4">
                            <span><span class="fw-semibold d-block">{{ $advance->employee->name }}</span><span class="small text-secondary">{{ $advance->business_date->format('d M Y') }}{{ $advance->notes ? ' · '.$advance->notes : '' }}</span></span>
                            <span class="ft-num fw-bold">@money($advance->amount)</span>
                        </li>
                    @empty
                        <li class="list-group-item empty-state">No advances in this range.</li>
                    @endforelse
                </ul>
            </div>
        </div>
        <div class="col-xl-7">
            <div class="card h-100">
                <div class="card-header d-flex"><span><i class="bi bi-shield-lock me-2"></i>Payroll audit trail</span><a href="{{ route('activity.index', ['module' => \App\Models\SalarySlip::class]) }}" class="small fw-normal text-decoration-none">Full trail</a></div>
                <ul class="list-group list-group-flush">
                    @forelse ($report['audit'] as $log)
                        <li class="list-group-item px-4">
                            <div class="d-flex justify-content-between gap-2">
                                <span><span class="badge {{ $log->eventBadge() }} me-1">{{ ucfirst($log->event) }}</span><span class="fw-semibold">{{ $log->description }}</span></span>
                                <span class="small text-secondary text-nowrap">{{ $log->created_at->format('d M, h:i A') }}</span>
                            </div>
                            <div class="small text-secondary">{{ $log->module() }} · by {{ $log->user?->name ?? 'System' }}</div>
                        </li>
                    @empty
                        <li class="list-group-item empty-state">No payroll changes in this range.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</x-layouts.app>
