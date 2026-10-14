<x-layouts.app title="Khata register" subtitle="Customer udhaar (credit sales) and payments received, in one place">
    @php($owedLiters = $owed->sum(fn ($credit) => $credit->owedRemaining()))

    <section class="hero-3d mb-4 khata-hero" data-tilt="1.2">
        <div class="row g-3 align-items-stretch">
            <div class="col-sm-6 col-xl-3">
                <div class="hero-tile h-100">
                    <div class="hero-label"><i class="bi bi-people me-1"></i>Customers owe in total</div>
                    <div class="hero-value">@money(round($receivable))</div>
                    <a href="{{ route('customers.index') }}" class="hero-sub text-decoration-none">{{ $payCustomers->where('balance', '>', 0)->count() }} accounts with a balance <i class="bi bi-arrow-right"></i></a>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="hero-tile h-100">
                    <div class="hero-label"><i class="bi bi-journal-plus me-1"></i>Udhaar given</div>
                    <div class="hero-value is-white">@money(round($totals['credit']))</div>
                    <div class="hero-sub">{{ $from->format('d M') }} – {{ $to->format('d M') }}{{ $totals['liters'] > 0 ? ' · '.\App\Support\Format::liters($totals['liters']) : '' }}</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="hero-tile h-100">
                    <div class="hero-label"><i class="bi bi-cash-coin me-1"></i>Payments received</div>
                    <div class="hero-value is-white">@money(round($totals['received']))</div>
                    <div class="hero-sub">{{ $from->format('d M') }} – {{ $to->format('d M') }}</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="hero-tile h-100">
                    <div class="hero-label"><i class="bi bi-hourglass-split me-1"></i>Fuel still to give</div>
                    <div @class(['hero-value', 'is-white' => $owedLiters <= 0])>@liters($owedLiters)</div>
                    <div class="hero-sub">{{ $owed->count() }} open {{ str('slip')->plural($owed->count()) }}</div>
                </div>
            </div>
        </div>

        <div class="khata-actions mt-3">
            <a href="{{ route('credits.create') }}" class="btn btn-warning"><i class="bi bi-journal-plus me-1"></i> Credit sale</a>
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#receivePaymentModal"><i class="bi bi-cash-coin me-1"></i> Receive payment</button>
            @if (auth()->user()->isOwner())
                <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#newCustomerModal"><i class="bi bi-person-plus me-1"></i> New customer</button>
            @endif
            <a href="{{ route('customers.index') }}" class="btn btn-light ms-sm-auto"><i class="bi bi-person-vcard me-1"></i> All customers</a>
        </div>
    </section>

    @include('credits.partials.owed-fuel', ['owed' => $owed])

    <div class="card">
        <div class="card-header khata-filter">
            <form method="GET" class="d-flex flex-wrap align-items-end gap-2 w-100">
                <div class="me-auto">
                    <div class="fw-bold">Entries</div>
                    <div class="small text-secondary fw-normal">Every credit sale and payment, newest first</div>
                </div>
                <div>
                    <label for="from" class="form-label small mb-1">From</label>
                    <input type="date" id="from" name="from" value="{{ $from->toDateString() }}" max="{{ today()->toDateString() }}" class="form-control form-control-sm">
                </div>
                <div>
                    <label for="to" class="form-label small mb-1">To</label>
                    <input type="date" id="to" name="to" value="{{ $to->toDateString() }}" max="{{ today()->toDateString() }}" class="form-control form-control-sm">
                </div>
                <div>
                    <label for="customer" class="form-label small mb-1">Customer</label>
                    <select id="customer" name="customer" class="form-select form-select-sm">
                        <option value="">All customers</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" @selected(($filters['customer'] ?? null) == $customer->id)>{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="kind" class="form-label small mb-1">Show</label>
                    <select id="kind" name="kind" class="form-select form-select-sm">
                        <option value="">Credit &amp; payments</option>
                        <option value="credit" @selected(($filters['kind'] ?? null) === 'credit')>Credit sales</option>
                        <option value="payment" @selected(($filters['kind'] ?? null) === 'payment')>Payments</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-dark btn-sm" aria-label="Apply filters"><i class="bi bi-funnel"></i></button>
            </form>
        </div>
        @error('to') <div class="alert alert-danger m-3">{{ $message }}</div> @enderror
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
                                            @include('credits.partials.owed-badge', ['credit' => $m])
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

    @include('khata.receive-modal')
    @include('khata.customer-modal')
</x-layouts.app>
