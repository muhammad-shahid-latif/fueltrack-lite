<x-layouts.app title="Payroll" subtitle="Monthly salaries, advance recovery and payments">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('payroll.index', ['month' => $previous->format('Y-m')]) }}" class="btn btn-light" aria-label="Previous month"><i class="bi bi-chevron-left"></i></a>
            <form method="GET" action="{{ route('payroll.index') }}">
                <label for="month" class="visually-hidden">Month</label>
                <input type="month" id="month" name="month" value="{{ $month->format('Y-m') }}" max="{{ today()->format('Y-m') }}"
                       class="form-control fw-semibold" onchange="this.form.submit()">
            </form>
            @if ($next)
                <a href="{{ route('payroll.index', ['month' => $next->format('Y-m')]) }}" class="btn btn-light" aria-label="Next month"><i class="bi bi-chevron-right"></i></a>
            @endif
        </div>
        <a href="{{ route('employees.index') }}" class="btn btn-light"><i class="bi bi-people me-1"></i> Employees</a>
    </div>

    @if ($todayIsClosed && $totals['pending_count'] > 0)
        <div class="alert alert-info d-flex align-items-center gap-2">
            <i class="bi bi-info-circle-fill"></i>
            <div>Today is closed. Salaries can still be paid: <strong>bank transfers</strong> are booked today, <strong>cash</strong> is booked on {{ $cashBookedOn->format('d M Y') }} so today's cash count stays correct.</div>
        </div>
    @endif

    @if ($missing > 0 && $canPrepare)
        <div class="card mb-4 border-warning-subtle">
            <div class="card-body p-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <div class="fw-bold">{{ $slips->isEmpty() ? 'Prepare the '.$month->format('F Y').' salary sheet' : $missing.' active '.str('employee')->plural($missing).' not on this sheet yet' }}</div>
                    <div class="small text-secondary">Basic salaries are filled in and outstanding advances are deducted automatically. You can adjust each slip before paying.</div>
                </div>
                <form method="POST" action="{{ route('payroll.prepare') }}" x-data="{ submitting: false }" @submit="submitting = true">
                    @csrf
                    <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
                    <button type="submit" class="btn btn-warning" :disabled="submitting">
                        <i class="bi bi-magic me-1"></i> {{ $slips->isEmpty() ? 'Prepare salary sheet' : 'Add them' }}
                    </button>
                </form>
            </div>
        </div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <x-stat-tile label="Earned" :value="\App\Support\Format::money($totals['gross'])" icon="briefcase-fill" tone="teal" hint="After absences, incl. bonus" />
        </div>
        <div class="col-sm-6 col-xl-3">
            <x-stat-tile label="Advances recovered" :value="\App\Support\Format::money($totals['advances'])" icon="arrow-repeat" tone="cash" />
        </div>
        <div class="col-sm-6 col-xl-3">
            <x-stat-tile label="Paid" :value="\App\Support\Format::money($totals['paid'])" icon="check2-circle" tone="sales" />
        </div>
        <div class="col-sm-6 col-xl-3">
            <x-stat-tile label="Still to pay" :value="\App\Support\Format::money($totals['pending'])" icon="hourglass-split" tone="expense"
                         :hint="$totals['pending_count'].' '.str('slip')->plural($totals['pending_count'])" />
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span>{{ $month->format('F Y') }} salary sheet</span>
            @if ($totals['pending_count'] > 0)
                <form method="POST" action="{{ route('payroll.pay-all') }}" class="d-flex align-items-center gap-2" x-data="{ sure: false }">
                    @csrf
                    <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
                    <button type="button" class="btn btn-sm btn-dark" x-show="! sure" @click="sure = true">
                        <i class="bi bi-cash-stack me-1"></i> Pay all pending
                    </button>
                    <span x-show="sure" x-cloak><span class="d-flex flex-wrap align-items-center gap-2">
                        <span class="small fw-normal">Pay @money($totals['pending']) by</span>
                        <select name="payment_method" class="form-select form-select-sm w-auto" aria-label="Payment method">
                            @foreach ($paymentMethods as $method)
                                <option value="{{ $method->value }}">{{ $method->label() }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-sm btn-warning">Confirm</button>
                        <button type="button" class="btn btn-sm btn-light" @click="sure = false">Cancel</button>
                    </span></span>
                </form>
            @endif
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th class="text-end">Basic</th>
                        <th class="text-end d-none d-md-table-cell">Absent</th>
                        <th class="text-end d-none d-md-table-cell">Bonus</th>
                        <th class="text-end">Deductions</th>
                        <th class="text-end">Net salary</th>
                        <th>Status</th>
                        <th class="text-end"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($slips as $slip)
                        <tr>
                            <td>
                                <a href="{{ route('employees.show', $slip->employee) }}" class="d-flex align-items-center gap-2 text-reset text-decoration-none">
                                    <x-employee-avatar :employee="$slip->employee" size="sm" />
                                    <span>
                                        <span class="fw-semibold d-block">{{ $slip->employee->name }}</span>
                                        <span class="small text-secondary">{{ $slip->employee->designation }}</span>
                                    </span>
                                </a>
                            </td>
                            <td class="text-end ft-num">@money($slip->basic_salary)</td>
                            <td class="text-end ft-num d-none d-md-table-cell">
                                @if ((float) $slip->absent_days > 0)
                                    {{ \App\Support\Format::number($slip->absent_days) }}d
                                    <div class="small text-danger text-nowrap">−@money($slip->absence_deduction)</div>
                                @else
                                    <span class="text-secondary">—</span>
                                @endif
                            </td>
                            <td class="text-end ft-num d-none d-md-table-cell">{!! (float) $slip->bonus > 0 ? '+'.e(\App\Support\Format::money($slip->bonus)) : '<span class="text-secondary">—</span>' !!}</td>
                            <td class="text-end ft-num">
                                @php($deducted = (float) $slip->advance_deduction + (float) $slip->other_deduction)
                                @if ($deducted > 0)
                                    <span class="text-danger">−@money($deducted)</span>
                                    @if ((float) $slip->advance_deduction > 0)
                                        <div class="small text-secondary text-nowrap">Advance @money($slip->advance_deduction)</div>
                                    @endif
                                @else
                                    <span class="text-secondary">—</span>
                                @endif
                                @php($notDeducted = $slip->isPaid() ? 0 : round(($outstanding[$slip->employee_id] ?? 0) - (float) $slip->advance_deduction, 2))
                                @if ($notDeducted > 0)
                                    <a href="{{ route('payroll.slips.edit', $slip) }}" class="d-block small text-warning-emphasis text-nowrap text-decoration-none" title="Advance owed but not deducted on this slip">
                                        <i class="bi bi-exclamation-circle"></i> @money($notDeducted) more owed
                                    </a>
                                @endif
                            </td>
                            <td class="text-end ft-num fw-bold fs-6">@money($slip->net_salary)</td>
                            <td class="text-nowrap">
                                @if ($slip->isPaid())
                                    <span class="badge text-bg-success">Paid</span>
                                    <div class="small text-secondary">{{ $slip->paid_on->format('d M') }} · {{ $slip->payment_method->label() }}</div>
                                @else
                                    <span class="badge text-bg-warning">Pending</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                @if ($slip->isPaid())
                                    <a href="{{ route('payroll.slips.show', $slip) }}" class="btn btn-sm btn-outline-dark"><i class="bi bi-receipt me-1"></i>Slip</a>
                                    <form method="POST" action="{{ route('payroll.slips.reverse', $slip) }}" class="d-inline" x-data="{ sure: false }">
                                        @csrf
                                        @method('DELETE')
                                        <button type="button" class="btn btn-sm btn-link text-danger" x-show="! sure" @click="sure = true">Reverse</button>
                                        <span x-show="sure" x-cloak>
                                            <button type="submit" class="btn btn-sm btn-danger">Undo payment</button>
                                            <button type="button" class="btn btn-sm btn-light" @click="sure = false">Keep</button>
                                        </span>
                                    </form>
                                @else
                                    <a href="{{ route('payroll.slips.edit', $slip) }}" class="btn btn-sm btn-outline-dark"><i class="bi bi-pencil me-1"></i>Adjust</a>
                                    <div class="btn-group">
                                            <button type="button" class="btn btn-sm btn-warning dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"
                                                    data-bs-popper-config='{"strategy":"fixed"}'>Pay</button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-4 p-2">
                                                @foreach ($paymentMethods as $method)
                                                    <li>
                                                        <form method="POST" action="{{ route('payroll.slips.pay', $slip) }}">
                                                            @csrf
                                                            <input type="hidden" name="payment_method" value="{{ $method->value }}">
                                                            <button type="submit" class="dropdown-item rounded-3">
                                                                Pay @money($slip->net_salary) by {{ strtolower($method->label()) }}
                                                            </button>
                                                        </form>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="empty-state">
                                <i class="bi bi-cash-coin"></i>
                                No salary sheet for {{ $month->format('F Y') }} yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($slips->isNotEmpty())
                    <tfoot>
                        <tr class="fw-bold">
                            <td>Total</td>
                            <td class="text-end ft-num">@money($slips->sum(fn ($slip) => (float) $slip->basic_salary))</td>
                            <td class="d-none d-md-table-cell"></td>
                            <td class="d-none d-md-table-cell"></td>
                            <td></td>
                            <td class="text-end ft-num">@money($totals['net'])</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
        <div class="card-footer bg-transparent small text-secondary px-4">
            Payments are saved as salary expenses for the day they are paid, so they are counted in that day's cash closing and in profit &amp; loss.
        </div>
    </div>
</x-layouts.app>
