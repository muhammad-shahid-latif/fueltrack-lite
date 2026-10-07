<x-layouts.app title="Sales" subtitle="Fuel from meter readings and items sold at the counter">
    @php($isOwner = auth()->user()->isOwner())

    <div class="d-flex flex-wrap justify-content-end gap-2 mb-3">
        <a href="{{ route('sales.create') }}" class="btn btn-warning"><i class="bi bi-bag-plus me-1"></i> Counter sale</a>
    </div>

    <form method="GET" action="{{ route('sales.index') }}" class="card mb-4">
        <div class="card-body row g-2 align-items-end">
            <div class="col-6 col-lg-2">
                <label for="from" class="form-label small mb-1">From</label>
                <input type="date" id="from" name="from" value="{{ $filters['from']?->toDateString() }}" max="{{ today()->toDateString() }}" class="form-control">
            </div>
            <div class="col-6 col-lg-2">
                <label for="to" class="form-label small mb-1">To</label>
                <input type="date" id="to" name="to" value="{{ $filters['to']?->toDateString() }}" max="{{ today()->toDateString() }}" class="form-control">
            </div>
            <div class="col-md-6 col-lg-3">
                <label for="product" class="form-label small mb-1">Product</label>
                <select id="product" name="product" class="form-select">
                    <option value="">All products</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" @selected($filters['product'] == $product->id)>{{ $product->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6 col-lg-2">
                <label for="source" class="form-label small mb-1">Sold through</label>
                <select id="source" name="source" class="form-select">
                    <option value="">Meter and counter</option>
                    <option value="meter" @selected($filters['source'] === 'meter')>Meter readings</option>
                    <option value="counter" @selected($filters['source'] === 'counter')>Counter</option>
                </select>
            </div>
            <div class="col-lg-3 d-flex gap-2">
                <button type="submit" class="btn btn-dark flex-grow-1"><i class="bi bi-funnel me-1"></i> Filter</button>
                @if ($isFiltered)
                    <a href="{{ route('sales.index') }}" class="btn btn-light">Show all</a>
                @endif
            </div>
        </div>
    </form>

    @error('to') <div class="alert alert-danger">{{ $message }}</div> @enderror

    <div class="row g-3 mb-4">
        <div class="col-md-6 col-xxl-3">
            <x-stat-tile label="Total sales" :value="\App\Support\Format::money(round($totals->amount))" icon="cash-stack" tone="sales"
                         :hint="$isFiltered ? 'Filtered period' : 'All dates'" />
        </div>
        <div class="col-md-6 col-xxl-3">
            <x-stat-tile label="Fuel sales" :value="\App\Support\Format::money(round($totals->fuel_amount))" icon="fuel-pump" tone="fuel" />
        </div>
        <div class="col-md-6 col-xxl-3">
            <x-stat-tile label="Counter items" :value="\App\Support\Format::money(round($totals->amount - $totals->fuel_amount))" icon="basket" tone="violet" />
        </div>
        <div class="col-md-6 col-xxl-3">
            <x-stat-tile label="Fuel sold" :value="\App\Support\Format::liters(round($totals->liters))" icon="droplet-fill" tone="teal"
                         :hint="number_format($totals->entries).' entries'" />
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Product</th>
                        <th>Sold through</th>
                        <th class="text-end">Quantity</th>
                        <th class="text-end">Rate</th>
                        <th class="text-end">Amount</th>
                        @if ($isOwner)
                            <th class="text-end"><span class="visually-hidden">Actions</span></th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sales as $sale)
                        <tr>
                            <td class="text-nowrap">{{ $sale->business_date->format('d M Y') }}</td>
                            <td>
                                <span class="d-flex align-items-center gap-2 {{ \App\Support\Grade::cssClass($sale->fuelProduct) }}">
                                    <span class="grade-dot"></span>{{ $sale->fuelProduct->name }}
                                </span>
                            </td>
                            <td class="text-nowrap">
                                @if ($sale->isCounterSale())
                                    <span class="badge text-bg-light border"><i class="bi bi-basket me-1"></i>Counter</span>
                                    @if ($sale->customer)
                                        <a href="{{ route('customers.show', $sale->customer) }}" class="badge text-bg-warning text-decoration-none"><i class="bi bi-journal-bookmark me-1"></i>Credit · {{ $sale->customer->name }}</a>
                                    @endif
                                @else
                                    <span class="small">{{ $sale->meterReading->nozzle->label() }}</span>
                                @endif
                            </td>
                            <td class="text-end ft-num">@qty($sale->quantity, $sale->fuelProduct->unit)</td>
                            <td class="text-end ft-num">@money($sale->rate)</td>
                            <td class="text-end ft-num fw-bold">@money($sale->amount)</td>
                            @if ($isOwner)
                                @php($closing = $closings->get($sale->business_date->toDateString()))
                                <td class="text-end text-nowrap">
                                    @if ($closing)
                                        <a href="{{ route('closings.show', $closing) }}" class="btn btn-sm btn-light" title="Day closed. Reopen it to edit.">
                                            <i class="bi bi-lock-fill"></i><span class="visually-hidden">Day closed</span>
                                        </a>
                                    @elseif ($sale->isCounterSale())
                                        <a href="{{ route('sales.edit', $sale) }}" class="btn btn-sm btn-outline-dark">
                                            <i class="bi bi-pencil me-1"></i>Edit
                                        </a>
                                    @else
                                        <a href="{{ route('meter-readings.edit', $sale->meter_reading_id) }}" class="btn btn-sm btn-outline-secondary" title="Fuel sales are corrected through their meter reading">
                                            <i class="bi bi-speedometer2 me-1"></i>Reading
                                        </a>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isOwner ? 7 : 6 }}" class="empty-state">
                                <i class="bi bi-receipt"></i>
                                No sales match these filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $sales->links() }}</div>
</x-layouts.app>
