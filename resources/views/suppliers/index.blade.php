<x-layouts.app title="Suppliers" subtitle="Supplier register, payables and payment schedule">
    <div x-data="{ tab: 'register' }">
        <section class="hero-3d mb-4" data-tilt="1.2">
            <div class="row g-3 align-items-center">
                <div class="col-lg-3">
                    <div class="hero-label">Total payable</div>
                    <div class="hero-value">@money(round($totals['payable']))</div>
                    <div class="hero-sub">to {{ $summaries->where('balance', '>', 0)->count() }} suppliers</div>
                </div>
                <div class="col-sm-4 col-lg-3">
                    <div class="hero-tile">
                        <div class="hero-label"><i class="bi bi-exclamation-octagon me-1"></i>Overdue</div>
                        <div @class(['hero-value', 'is-white' => $totals['overdue'] <= 0])>@money(round($totals['overdue']))</div>
                    </div>
                </div>
                <div class="col-sm-4 col-lg-3">
                    <div class="hero-tile">
                        <div class="hero-label"><i class="bi bi-calendar-event me-1"></i>Due in {{ \App\Services\SupplierService::DUE_SOON_DAYS }} days</div>
                        <div class="hero-value is-white">@money(round($totals['due_soon']))</div>
                    </div>
                </div>
                <div class="col-sm-4 col-lg-3 text-lg-end">
                    <a href="{{ route('suppliers.create') }}" class="btn btn-warning btn-lg"><i class="bi bi-plus-lg me-1"></i> Add supplier</a>
                </div>
            </div>
        </section>

        <div class="seg mb-4">
            <a href="#" :class="tab === 'register' && 'active'" @click.prevent="tab = 'register'"><i class="bi bi-building me-1"></i> Register</a>
            <a href="#" :class="tab === 'schedule' && 'active'" @click.prevent="tab = 'schedule'"><i class="bi bi-calendar3 me-1"></i> Payment schedule <span class="badge text-bg-danger ms-1">{{ $schedule->count() }}</span></a>
        </div>

        <div x-show="tab === 'register'">
            <div class="row g-4">
                @forelse ($summaries as $row)
                    @php($supplier = $row['supplier'])
                    <div class="col-md-6 col-xxl-4">
                        <a href="{{ route('suppliers.show', $supplier) }}" @class(['card is-lift h-100 text-decoration-none text-reset', 'opacity-50' => ! $supplier->is_active])>
                            <div class="card-body p-4">
                                <div class="d-flex gap-3 align-items-center mb-3">
                                    <span class="party-avatar is-supplier">{{ str($supplier->name)->substr(0, 1)->upper() }}</span>
                                    <div style="min-width: 0;">
                                        <div class="fw-bold fs-5 text-truncate">{{ $supplier->name }}</div>
                                        <div class="small text-secondary text-truncate">{{ $supplier->contact_person ?? '—' }} · {{ $supplier->phone ?? 'no phone' }}</div>
                                    </div>
                                    @if ($row['overdue'] > 0)
                                        <span class="badge text-bg-danger ms-auto">Overdue</span>
                                    @elseif ($row['balance'] <= 0)
                                        <span class="badge text-bg-success ms-auto">Settled</span>
                                    @endif
                                </div>
                                <div class="row g-2 text-center">
                                    <div class="col-4"><div class="small text-secondary">Purchased</div><div class="fw-bold ft-num">@money(round($row['purchased']))</div></div>
                                    <div class="col-4"><div class="small text-secondary">Paid</div><div class="fw-bold ft-num amount-in">@money(round($row['paid']))</div></div>
                                    <div class="col-4"><div class="small text-secondary">Payable</div><div @class(['fw-bold ft-num', 'amount-out' => $row['balance'] > 0])>@money(round($row['balance']))</div></div>
                                </div>
                                <div class="small text-secondary mt-3 d-flex justify-content-between">
                                    <span><i class="bi bi-clock-history me-1"></i>{{ $supplier->termsLabel() }}</span>
                                    <span>
                                        @if ($row['overdue'] > 0)
                                            <span class="amount-out fw-semibold">@money(round($row['overdue'])) overdue</span>
                                        @elseif ($row['next_due'])
                                            Next due {{ $row['next_due']->format('d M') }}
                                        @endif
                                    </span>
                                </div>
                            </div>
                        </a>
                    </div>
                @empty
                    <div class="col-12"><div class="card"><div class="card-body empty-state"><i class="bi bi-building"></i>No suppliers yet.</div></div></div>
                @endforelse
            </div>
        </div>

        <div x-show="tab === 'schedule'" x-cloak>
            <div class="card">
                <div class="card-header">Open bills, soonest due first</div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr><th>Due</th><th>Supplier</th><th>Bill</th><th class="text-end">Amount</th><th class="text-end">Paid</th><th class="text-end">Remaining</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($schedule as $bill)
                                <tr>
                                    <td class="text-nowrap fw-semibold">{{ $bill['due']->format('d M Y') }}
                                        <div class="small text-secondary">{{ $bill['days_overdue'] > 0 ? $bill['days_overdue'].' days late' : ($bill['due']->isToday() ? 'today' : $bill['due']->diffForHumans(today(), ['syntax' => \Carbon\CarbonInterface::DIFF_RELATIVE_TO_NOW])) }}</div>
                                    </td>
                                    <td><a href="{{ route('suppliers.show', $bill['supplier']) }}" class="fw-semibold text-decoration-none">{{ $bill['supplier']->name }}</a></td>
                                    <td>{{ $bill['label'] }}<div class="small text-secondary">{{ $bill['date']->format('d M Y') }}</div></td>
                                    <td class="text-end ft-num">@money($bill['amount'])</td>
                                    <td class="text-end ft-num amount-in">@money($bill['paid'])</td>
                                    <td class="text-end ft-num fw-bold">@money($bill['remaining'])</td>
                                    <td><span class="badge {{ $bill['status']->badge() }}">{{ $bill['status']->label() }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="empty-state"><i class="bi bi-check2-circle"></i>Every supplier bill is paid.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
