<x-layouts.app title="Customers" subtitle="Khata accounts: who owes what, and since when">
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
            <div class="col-lg-2 d-flex flex-wrap flex-lg-column gap-2">
                <a href="{{ route('credits.create') }}" class="btn btn-warning"><i class="bi bi-journal-plus me-1"></i> Credit sale</a>
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#receivePaymentModal"><i class="bi bi-cash-coin me-1"></i> Receive</button>
                @if (auth()->user()->isOwner())
                    <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#newCustomerModal"><i class="bi bi-person-plus me-1"></i> New customer</button>
                @endif
            </div>
        </div>
    </section>

    @php($chips = [
        'all' => ['All', $summaries->count()],
        'owes' => ['Owes money', $summaries->where('balance', '>', 0)->count()],
        'overdue' => ['Overdue', $summaries->where('overdue', '>', 0)->count()],
        'over' => ['Over limit', $totals['over_limit']],
        'inactive' => ['Closed', $summaries->filter(fn ($row) => ! $row['customer']->is_active)->count()],
    ])
    <div class="card" x-data="customerFilter({{ $summaries->count() }})">
        <div class="card-header khata-filter">
            <div class="d-flex flex-wrap align-items-center gap-2 w-100">
                <div class="customer-search">
                    <i class="bi bi-search"></i>
                    <input type="search" x-model="q" class="form-control" placeholder="Search name, vehicle or phone…" aria-label="Search customers">
                </div>
                <div class="d-flex flex-wrap gap-1">
                    @foreach ($chips as $key => [$label, $count])
                        @if ($key === 'all' || $count > 0)
                            <button type="button" class="filter-chip" :class="show === '{{ $key }}' && 'active'" @click="show = '{{ $key }}'">{{ $label }} <span>{{ $count }}</span></button>
                        @endif
                    @endforeach
                </div>
                <span class="small text-secondary fw-normal ms-auto" x-text="`Showing ${visible} of ${total}`"></span>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr><th>Customer</th><th class="text-end">Credit given</th><th class="text-end">Received</th><th class="text-end">Balance</th><th style="min-width: 160px;">Credit limit</th><th class="d-none d-lg-table-cell">Last payment</th><th class="text-end d-print-none"></th></tr>
                </thead>
                <tbody>
                    @forelse ($summaries as $row)
                        @php($customer = $row['customer'])
                        @php($used = $customer->credit_limit ? min(100, max(0, round($row['balance'] / (float) $customer->credit_limit * 100))) : null)
                        <tr @class(['opacity-50' => ! $customer->is_active])
                            data-search="{{ mb_strtolower(collect([$customer->name, $customer->vehicle_number, $customer->phone])->filter()->join(' ')) }}"
                            data-owes="{{ $row['balance'] > 0 ? 1 : 0 }}" data-overdue="{{ $row['overdue'] > 0 ? 1 : 0 }}"
                            data-over="{{ $row['available'] !== null && $row['available'] < 0 ? 1 : 0 }}" data-inactive="{{ $customer->is_active ? 0 : 1 }}"
                            x-show="matches($el.dataset)">
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
                            <td class="text-end text-nowrap d-print-none">
                                @if ($customer->is_active && $row['balance'] > 0)
                                    <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#receivePaymentModal" data-customer-id="{{ $customer->id }}" title="Receive payment">
                                        <i class="bi bi-cash-coin"></i><span class="d-none d-xl-inline ms-1">Receive</span>
                                    </button>
                                @endif
                                <a href="{{ route('customers.show', $customer) }}" class="btn btn-sm btn-light" title="Open khata"><i class="bi bi-journal-text"></i><span class="d-none d-xl-inline ms-1">Khata</span></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="empty-state"><i class="bi bi-person-vcard"></i>No khata customers yet.@if (auth()->user()->isOwner())<div class="mt-3"><button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#newCustomerModal">Open the first khata</button></div>@endif</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body text-center text-secondary small" x-show="visible === 0 && total > 0" x-cloak>No customer matches this search.</div>
    </div>

    @include('khata.receive-modal')
    @include('khata.customer-modal')
</x-layouts.app>
