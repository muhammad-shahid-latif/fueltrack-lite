<x-layouts.app title="Debt register" subtitle="Every credit sale and every payment received">
    <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
        <form method="GET" class="d-flex flex-wrap align-items-end gap-2">
            <div>
                <label for="from" class="form-label small mb-1">From</label>
                <input type="date" id="from" name="from" value="{{ $from->toDateString() }}" max="{{ today()->toDateString() }}" class="form-control">
            </div>
            <div>
                <label for="to" class="form-label small mb-1">To</label>
                <input type="date" id="to" name="to" value="{{ $to->toDateString() }}" max="{{ today()->toDateString() }}" class="form-control">
            </div>
            <div>
                <label for="customer" class="form-label small mb-1">Customer</label>
                <select id="customer" name="customer" class="form-select">
                    <option value="">All customers</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}" @selected(($filters['customer'] ?? null) == $customer->id)>{{ $customer->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="kind" class="form-label small mb-1">Show</label>
                <select id="kind" name="kind" class="form-select">
                    <option value="">Credit &amp; payments</option>
                    <option value="credit" @selected(($filters['kind'] ?? null) === 'credit')>Credit sales</option>
                    <option value="payment" @selected(($filters['kind'] ?? null) === 'payment')>Payments</option>
                </select>
            </div>
            <button type="submit" class="btn btn-dark"><i class="bi bi-funnel"></i></button>
        </form>
        <div class="d-flex gap-2">
            <a href="{{ route('customers.index') }}" class="btn btn-light"><i class="bi bi-person-vcard me-1"></i> Balances</a>
            <a href="{{ route('credits.create') }}" class="btn btn-warning"><i class="bi bi-plus-lg me-1"></i> Credit sale</a>
        </div>
    </div>
    @error('to') <div class="alert alert-danger">{{ $message }}</div> @enderror

    <div class="row g-3 mb-4">
        <div class="col-sm-4"><x-stat-tile label="Credit given" :value="\App\Support\Format::money(round($totals['credit']))" icon="journal-plus" tone="expense" :hint="$totals['liters'] > 0 ? \App\Support\Format::liters($totals['liters']).' of fuel' : null" /></div>
        <div class="col-sm-4"><x-stat-tile label="Payments received" :value="\App\Support\Format::money(round($totals['received']))" icon="cash-coin" tone="sales" /></div>
        <div class="col-sm-4"><x-stat-tile label="Net change in debt" :value="\App\Support\Format::money(round($totals['credit'] - $totals['received']))" icon="arrow-down-up" tone="violet" /></div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr><th>When</th><th>Customer</th><th>Entry</th><th class="text-end">Credit</th><th class="text-end">Received</th><th class="d-none d-md-table-cell">By</th></tr>
                </thead>
                <tbody>
                    @forelse ($entries as $entry)
                        @php($m = $entry['model'])
                        <tr>
                            <td class="text-nowrap">{{ $entry['date']->format('d M Y') }}<div class="small text-secondary">{{ $entry['at']->format('h:i A') }}</div></td>
                            <td><a href="{{ route('customers.show', $m->customer) }}" class="fw-semibold text-decoration-none">{{ $m->customer->name }}</a></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    @if ($entry['kind'] === 'credit')
                                        <span class="icon-chip is-out"><i class="bi bi-{{ $m->isCounterSale() ? 'bag' : 'fuel-pump' }}"></i></span>
                                        <span>
                                            <span class="fw-semibold d-block">{{ $m->fuelProduct ? \App\Support\Format::quantity($m->quantity, $m->fuelProduct->unit).' '.$m->fuelProduct->name : 'Credit' }}</span>
                                            <span class="small text-secondary">{{ collect([$m->vehicle_number, $m->slip_number ? 'Slip '.$m->slip_number : null, $m->isCounterSale() ? 'Counter sale' : null])->filter()->join(' · ') ?: '—' }}</span>
                                        </span>
                                    @else
                                        <span class="icon-chip is-in"><i class="bi bi-cash-coin"></i></span>
                                        <span><span class="fw-semibold d-block">Payment · {{ $m->payment_method->label() }}</span><span class="small text-secondary">{{ $m->reference ?? '—' }}</span></span>
                                    @endif
                                </div>
                            </td>
                            <td class="text-end ft-num fw-bold amount-out">{{ $entry['kind'] === 'credit' ? \App\Support\Format::money($m->amount) : '' }}</td>
                            <td class="text-end ft-num fw-bold amount-in">{{ $entry['kind'] === 'payment' ? \App\Support\Format::money($m->amount) : '' }}</td>
                            <td class="d-none d-md-table-cell small text-secondary">{{ $m->recorder?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty-state"><i class="bi bi-journal-bookmark"></i>No credit activity in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
