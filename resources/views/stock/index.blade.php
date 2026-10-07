<x-layouts.app title="Stock" subtitle="Opening + received − sold ± dip adjustments = closing, worked out from the ledger">
    <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
        <form method="GET" action="{{ route('stock.index') }}" class="d-flex align-items-end gap-2">
            <div>
                <label for="date" class="form-label small mb-1">Stock as of</label>
                <input type="date" id="date" name="date" value="{{ $date->toDateString() }}" max="{{ today()->toDateString() }}"
                       class="form-control" onchange="this.form.submit()">
            </div>
            @unless ($date->isToday())
                <a href="{{ route('stock.index') }}" class="btn btn-light">Today</a>
            @endunless
        </form>

        <div class="d-flex gap-2">
            <a href="{{ route('tanks.index') }}" class="btn btn-light"><i class="bi bi-database-fill me-1"></i> Tanks</a>
            <a href="{{ route('purchases.create') }}" class="btn btn-warning"><i class="bi bi-truck me-1"></i> Receive delivery</a>
        </div>
    </div>

    @php($fuelRows = $rows->filter(fn ($row) => $row['product']->isMetered()))
    @php($itemRows = $rows->reject(fn ($row) => $row['product']->isMetered()))

    <div class="row g-3 mb-4">
        @foreach ($fuelRows as $row)
            @php($capacity = max($row['closing'], (float) $row['product']->low_stock_threshold * 4, 1))
            <div class="col-md-6 col-xl-4">
                <div class="card h-100 {{ \App\Support\Grade::cssClass($row['product']) }}">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="d-flex align-items-center gap-2 fw-semibold"><span class="grade-dot"></span>{{ $row['product']->name }}</span>
                            @if ($row['is_low'])
                                <span class="badge text-bg-danger"><i class="bi bi-exclamation-triangle me-1"></i>Low</span>
                            @else
                                <span class="badge text-bg-success">Good</span>
                            @endif
                        </div>
                        <div class="ft-num fw-bold mb-3" style="font-size: 2rem;">@liters($row['closing'])</div>
                        <div class="progress mb-2" style="height: 12px; border-radius: 99px;" role="progressbar" aria-label="{{ $row['product']->name }} stock level">
                            <div class="progress-bar" style="width: {{ min(100, round($row['closing'] / $capacity * 100)) }}%; background: var(--grade); border-radius: 99px;"></div>
                        </div>
                        <div class="small text-secondary">Reorder at @liters($row['product']->low_stock_threshold)</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card">
        <div class="card-header">Movement on {{ $date->format('d M Y') }}</div>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th class="text-end">Opening</th>
                        <th class="text-end">Received</th>
                        <th class="text-end">Sold</th>
                        <th class="text-end">Adjusted</th>
                        <th class="text-end">Closing</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ([$fuelRows, $itemRows] as $group)
                        @foreach ($group as $row)
                            @php($unit = $row['product']->unit)
                            <tr>
                                <td>
                                    <span class="d-flex align-items-center gap-2 {{ \App\Support\Grade::cssClass($row['product']) }}">
                                        <span class="grade-dot"></span><span class="fw-semibold">{{ $row['product']->name }}</span>
                                    </span>
                                </td>
                                <td class="text-end ft-num">@qty($row['opening'], $unit)</td>
                                <td class="text-end ft-num text-success">+ @qty($row['received'], $unit)</td>
                                <td class="text-end ft-num text-danger">− @qty($row['sold'], $unit)</td>
                                <td class="text-end ft-num text-secondary">{{ $row['adjusted'] != 0 ? ($row['adjusted'] > 0 ? '+ ' : '− ').\App\Support\Format::quantity(abs($row['adjusted']), $unit) : '—' }}</td>
                                <td class="text-end ft-num fw-bold">@qty($row['closing'], $unit)</td>
                                <td>
                                    @if ($row['is_low'])
                                        <span class="badge text-bg-danger">Low</span>
                                    @else
                                        <span class="badge text-bg-success">Good</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
