<x-layouts.app title="Customers" subtitle="Credit customers, outstanding balances and aging">
    @php($agingTotal = max(0.01, array_sum($aging)))

    <section class="hero-3d mb-4" data-tilt="1.2">
        <div class="row g-3 align-items-center">
            <div class="col-lg-3">
                <div class="hero-label">Total receivable</div>
                <div class="hero-value">@money(round($totals['receivable']))</div>
                <div class="hero-sub">{{ $totals['accounts'] }} accounts with a balance</div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="hero-tile">
                    <div class="hero-label"><i class="bi bi-hourglass-split me-1"></i>Over {{ \App\Services\CreditService::TERMS_DAYS }} days</div>
                    <div @class(['hero-value', 'is-white' => $totals['overdue'] <= 0])>@money(round($totals['overdue']))</div>
                    <div class="hero-sub">{{ $totals['over_limit'] }} over their credit limit</div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-4">
                <div class="hero-tile">
                    <div class="hero-label mb-2">Aging</div>
                    <div class="aging-bar mb-2">
                        @foreach (array_values($aging) as $i => $amount)
                            <span class="aging-{{ $i }}" style="width: {{ round($amount / $agingTotal * 100, 1) }}%"></span>
                        @endforeach
                    </div>
                    <div class="d-flex flex-wrap gap-3 hero-sub">
                        @foreach (array_keys($aging) as $i => $bucket)
                            <span><span class="d-inline-block rounded-circle aging-{{ $i }} me-1" style="width: 8px; height: 8px;"></span>{{ $bucket }}: {{ \App\Support\Format::money(round($aging[$bucket])) }}</span>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="col-lg-2 text-lg-end d-flex flex-lg-column gap-2">
                <a href="{{ route('credits.create') }}" class="btn btn-warning"><i class="bi bi-plus-lg me-1"></i> Credit sale</a>
                @if (auth()->user()->isOwner())
                    <a href="{{ route('customers.create') }}" class="btn btn-light"><i class="bi bi-person-plus me-1"></i> Add customer</a>
                @endif
            </div>
        </div>
    </section>

    <div class="card">
        <div class="card-header d-flex">
            <span>Customer ledgers</span>
            <a href="{{ route('credits.index') }}" class="small fw-normal text-decoration-none">Debt register history <i class="bi bi-arrow-right"></i></a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr><th>Customer</th><th class="text-end">Credit given</th><th class="text-end">Received</th><th class="text-end">Balance</th><th style="min-width: 160px;">Credit limit</th><th class="d-none d-lg-table-cell">Last payment</th></tr>
                </thead>
                <tbody>
                    @forelse ($summaries as $row)
                        @php($customer = $row['customer'])
                        @php($used = $customer->credit_limit ? min(100, max(0, round($row['balance'] / (float) $customer->credit_limit * 100))) : null)
                        <tr @class(['opacity-50' => ! $customer->is_active])>
                            <td>
                                <a href="{{ route('customers.show', $customer) }}" class="d-flex align-items-center gap-3 text-decoration-none text-reset">
                                    <span class="party-avatar is-customer">{{ $customer->initials() }}</span>
                                    <span><span class="fw-bold d-block">{{ $customer->name }}</span><span class="small text-secondary">{{ $customer->vehicle_number ?? $customer->phone ?? '—' }}</span></span>
                                </a>
                            </td>
                            <td class="text-end ft-num">@money(round($row['credited']))</td>
                            <td class="text-end ft-num amount-in">@money(round($row['received']))</td>
                            <td class="text-end ft-num fw-bold">
                                @money($row['balance'])
                                @if ($row['overdue'] > 0)<div class="small amount-out">@money(round($row['overdue'])) overdue</div>@endif
                            </td>
                            <td>
                                @if ($used !== null)
                                    <div class="limit-meter"><span style="width: {{ $used }}%; background-position: {{ $used }}% 0;"></span></div>
                                    <div class="small text-secondary">{{ $used }}% of @money(round($customer->credit_limit))</div>
                                @else
                                    <span class="small text-secondary">No limit</span>
                                @endif
                            </td>
                            <td class="d-none d-lg-table-cell small">{{ $row['last_payment'] ? $row['last_payment']->business_date->format('d M Y').' · '.\App\Support\Format::money($row['last_payment']->amount) : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty-state"><i class="bi bi-person-vcard"></i>No credit customers yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
