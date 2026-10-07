<x-layouts.app :title="'Salary slip · '.$slip->employee->name" :subtitle="$slip->month->format('F Y')">
    <div class="d-flex flex-wrap justify-content-between gap-2 mb-4 d-print-none">
        <a href="{{ route('payroll.index', ['month' => $slip->month->format('Y-m')]) }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i> Payroll</a>
        <button type="button" class="btn btn-warning" onclick="window.print()"><i class="bi bi-printer me-1"></i> Print / Save PDF</button>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-9 col-xl-7">
            <div class="report-sheet card">
                <div class="card-body p-4 p-md-5">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 border-bottom pb-3 mb-4">
                        <div>
                            <h2 class="h5 fw-bold mb-0">{{ \App\Support\Station::name() }}</h2>
                            <div class="text-secondary small">{{ \App\Support\Station::address() }}</div>
                        </div>
                        <div class="text-end">
                            <div class="fw-bold">Salary Slip</div>
                            <div class="text-secondary">{{ $slip->month->format('F Y') }}</div>
                        </div>
                    </div>

                    <div class="row g-3 mb-4 small">
                        <div class="col-6">
                            <div class="d-flex align-items-center gap-3">
                                <x-employee-avatar :employee="$slip->employee" />
                                <div>
                                    <div class="fw-bold fs-6">{{ $slip->employee->name }}</div>
                                    <div>{{ $slip->employee->designation }}</div>
                                    @if ($slip->employee->cnic_number)
                                        <div class="text-secondary">CNIC {{ $slip->employee->cnic_number }}</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="col-6 text-end">
                            <div class="text-secondary">Status</div>
                            @if ($slip->isPaid())
                                <div class="fw-bold text-success">Paid {{ $slip->paid_on->format('d M Y') }}</div>
                                <div>{{ $slip->payment_method->label() }}{{ $slip->payer ? ', by '.$slip->payer->name : '' }}</div>
                            @else
                                <div class="fw-bold text-warning">Pending</div>
                            @endif
                        </div>
                    </div>

                    <div class="row g-4">
                        <div class="col-sm-6">
                            <h3 class="h6 fw-bold">Earnings</h3>
                            <table class="table table-sm mb-0">
                                <tr><td>Basic salary</td><td class="text-end">@money($slip->basic_salary)</td></tr>
                                <tr><td>Bonus / overtime</td><td class="text-end">@money($slip->bonus)</td></tr>
                                <tr class="fw-bold"><td>Total</td><td class="text-end">@money((float) $slip->basic_salary + (float) $slip->bonus)</td></tr>
                            </table>
                        </div>
                        <div class="col-sm-6">
                            <h3 class="h6 fw-bold">Deductions</h3>
                            <table class="table table-sm mb-0">
                                <tr><td>Absent ({{ \App\Support\Format::number($slip->absent_days) }} of {{ $slip->days_in_month }} days)</td><td class="text-end">@money($slip->absence_deduction)</td></tr>
                                <tr><td>Advance recovered</td><td class="text-end">@money($slip->advance_deduction)</td></tr>
                                <tr><td>Other</td><td class="text-end">@money($slip->other_deduction)</td></tr>
                                <tr class="fw-bold"><td>Total</td><td class="text-end">@money($slip->totalDeductions())</td></tr>
                            </table>
                        </div>
                    </div>

                    <div class="money-panel p-4 mt-4 d-flex justify-content-between align-items-center">
                        <span class="fw-semibold">Net salary</span>
                        <span class="money-panel-amount">@money($slip->net_salary)</span>
                    </div>

                    @if ($slip->notes)
                        <p class="small text-secondary mt-3 mb-0">Note: {{ $slip->notes }}</p>
                    @endif

                    <div class="row mt-5 pt-4 small text-secondary">
                        <div class="col-6"><div class="border-top pt-2">Employee signature</div></div>
                        <div class="col-6 text-end"><div class="border-top pt-2">Authorised by</div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
