<x-layouts.app title="Meter readings" subtitle="Opening and closing meters per nozzle, by shift">
    <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
        <form method="GET" action="{{ route('meter-readings.index') }}" class="d-flex flex-wrap align-items-end gap-2">
            <div>
                <label for="date" class="form-label small mb-1">Date</label>
                <input type="date" id="date" name="date" value="{{ $date->toDateString() }}" max="{{ today()->toDateString() }}"
                       class="form-control" onchange="this.form.submit()">
            </div>
            <div>
                <label for="shift" class="form-label small mb-1">Shift</label>
                <select id="shift" name="shift" class="form-select" onchange="this.form.submit()">
                    <option value="">All shifts</option>
                    @foreach ($shifts as $option)
                        <option value="{{ $option->value }}" @selected($shift === $option)>{{ $option->label() }}</option>
                    @endforeach
                </select>
            </div>
            @if (! $date->isToday() || $shift)
                <a href="{{ route('meter-readings.index') }}" class="btn btn-light">Today, all shifts</a>
            @endif
        </form>

        @unless ($summary['is_closed'])
            <a href="{{ route('meter-readings.create') }}" class="btn btn-warning">
                <i class="bi bi-plus-lg me-1"></i> New reading
            </a>
        @endunless
    </div>

    <div class="row g-3 mb-4">
        <div class="col-sm-4">
            <x-stat-tile label="Fuel sold" :value="\App\Support\Format::liters($summary['total_liters'])" icon="droplet-fill" tone="fuel" />
        </div>
        <div class="col-sm-4">
            <x-stat-tile label="Fuel sales" :value="\App\Support\Format::money($summary['fuel_sales'])" icon="cash-stack" tone="sales" />
        </div>
        <div class="col-sm-4">
            <x-stat-tile label="Day status" :value="$summary['is_closed'] ? 'Closed' : 'Open'" :icon="$summary['is_closed'] ? 'lock-fill' : 'unlock-fill'" :tone="$summary['is_closed'] ? 'teal' : 'cash'" />
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Pump and nozzle</th>
                        <th>Shift</th>
                        <th class="text-end">Opening</th>
                        <th class="text-end">Closing</th>
                        <th class="text-end">Liters</th>
                        <th class="text-end">Rate</th>
                        <th class="text-end">Amount</th>
                        <th class="d-none d-md-table-cell">Recorded</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($readings as $reading)
                        <tr>
                            <td class="text-nowrap">
                                <span class="d-flex align-items-center gap-2 {{ \App\Support\Grade::cssClass($reading->nozzle->fuelProduct) }}">
                                    <span class="grade-dot"></span>
                                    <span>
                                        <span class="fw-semibold d-block">{{ $reading->nozzle->label() }}</span>
                                        <span class="small text-secondary">{{ $reading->nozzle->fuelProduct->name }}</span>
                                    </span>
                                </span>
                            </td>
                            <td><span class="badge text-bg-light border">{{ $reading->shift->label() }}</span></td>
                            <td class="text-end ft-num">@meter($reading->opening_meter)</td>
                            <td class="text-end ft-num">@meter($reading->closing_meter)</td>
                            <td class="text-end ft-num">@liters($reading->liters)</td>
                            <td class="text-end ft-num">@money($reading->sale->rate)</td>
                            <td class="text-end ft-num fw-bold">@money($reading->sale->amount)</td>
                            <td class="d-none d-md-table-cell small text-secondary text-nowrap">
                                {{ $reading->recorder?->name ?? '—' }}<br>{{ $reading->created_at->format('h:i A') }}
                                @if ($reading->notes)
                                    <i class="bi bi-chat-left-text ms-1" title="{{ $reading->notes }}"></i>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="empty-state">
                                <i class="bi bi-fuel-pump"></i>
                                No readings for {{ $date->format('d M Y') }}{{ $shift ? ', '.$shift->label().' shift' : '' }}.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $readings->links() }}</div>
</x-layouts.app>
