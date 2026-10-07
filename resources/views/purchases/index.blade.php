<x-layouts.app title="Purchases" subtitle="Deliveries received, with tank, supplier and weighted average cost">
    <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
        <form method="GET" action="{{ route('purchases.index') }}" class="d-flex flex-wrap align-items-end gap-2">
            <div>
                <label for="from" class="form-label small mb-1">From</label>
                <input type="date" id="from" name="from" value="{{ $from->toDateString() }}" max="{{ today()->toDateString() }}" class="form-control">
            </div>
            <div>
                <label for="to" class="form-label small mb-1">To</label>
                <input type="date" id="to" name="to" value="{{ $to->toDateString() }}" max="{{ today()->toDateString() }}" class="form-control">
            </div>
            <button type="submit" class="btn btn-dark" aria-label="Filter"><i class="bi bi-funnel"></i></button>
        </form>

        <a href="{{ route('purchases.create') }}" class="btn btn-warning"><i class="bi bi-truck me-1"></i> Receive delivery</a>
    </div>

    @error('to') <div class="alert alert-danger">{{ $message }}</div> @enderror

    <div class="row g-3 mb-4">
        <div class="col-sm-4">
            <x-stat-tile label="Deliveries" :value="number_format($totals->deliveries)" icon="truck" tone="teal" />
        </div>
        <div class="col-sm-4">
            <x-stat-tile label="Fuel received" :value="\App\Support\Format::liters($totals->liters)" icon="droplet-fill" tone="fuel" />
        </div>
        <div class="col-sm-4">
            <x-stat-tile label="Purchase value" :value="\App\Support\Format::money($totals->amount)" icon="cash-coin" tone="cash" />
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Product</th>
                        <th class="text-end">Quantity</th>
                        <th class="text-end">Rate</th>
                        <th class="text-end">Total</th>
                        <th class="text-end d-none d-xl-table-cell">Avg cost after</th>
                        <th class="d-none d-md-table-cell">Supplier</th>
                        <th class="d-none d-lg-table-cell">Tank · invoice</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($purchases as $purchase)
                        <tr>
                            <td class="text-nowrap">{{ $purchase->business_date->format('d M Y') }}</td>
                            <td>
                                <span class="d-flex align-items-center gap-2 {{ \App\Support\Grade::cssClass($purchase->fuelProduct) }}">
                                    <span class="grade-dot"></span><span class="fw-semibold">{{ $purchase->fuelProduct->name }}</span>
                                </span>
                            </td>
                            <td class="text-end ft-num">@qty($purchase->quantity, $purchase->fuelProduct->unit)</td>
                            <td class="text-end ft-num">@money($purchase->rate)</td>
                            <td class="text-end ft-num fw-bold">@money($purchase->amount)</td>
                            <td class="text-end ft-num d-none d-xl-table-cell">{{ $purchase->average_cost !== null ? \App\Support\Format::money(round((float) $purchase->average_cost, 2)) : '—' }}</td>
                            <td class="d-none d-md-table-cell">
                                @if ($purchase->supplier)
                                    <a href="{{ auth()->user()->isOwner() ? route('suppliers.show', $purchase->supplier) : '#' }}" class="text-decoration-none fw-semibold">{{ $purchase->supplier->name }}</a>
                                    @if ($purchase->due_date)<div class="small text-secondary">due {{ $purchase->due_date->format('d M') }}</div>@endif
                                @else
                                    {{ $purchase->supplier_name ?? '—' }}
                                @endif
                            </td>
                            <td class="d-none d-lg-table-cell small text-secondary">{{ collect([$purchase->tank?->name, $purchase->invoice_number])->filter()->join(' · ') ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="empty-state"><i class="bi bi-truck"></i>No purchases in this period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $purchases->links() }}</div>
</x-layouts.app>
