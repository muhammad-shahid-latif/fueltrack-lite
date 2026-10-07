<x-layouts.app title="Nozzle performance" subtitle="Liters and sales per nozzle per day">
    @php($rows = $report['rows'])
    @php($maxLiters = max(1, $rows->max('liters') ?? 1))

    <form method="GET" class="d-flex flex-wrap align-items-end gap-2 mb-4">
        <div>
            <label for="from" class="form-label small mb-1">From</label>
            <input type="date" id="from" name="from" value="{{ $from->toDateString() }}" max="{{ today()->toDateString() }}" class="form-control">
        </div>
        <div>
            <label for="to" class="form-label small mb-1">To</label>
            <input type="date" id="to" name="to" value="{{ $to->toDateString() }}" max="{{ today()->toDateString() }}" class="form-control">
        </div>
        <button type="submit" class="btn btn-dark"><i class="bi bi-funnel me-1"></i> Show</button>
        <div class="seg ms-md-2">
            @foreach ([6 => '7 days', 13 => '14 days', 29 => '30 days'] as $days => $label)
                <a href="{{ route('reports.nozzles', ['from' => today()->subDays($days)->toDateString(), 'to' => today()->toDateString()]) }}"
                   @class(['active' => $from->isSameDay(today()->subDays($days)) && $to->isToday()])>{{ $label }}</a>
            @endforeach
        </div>
        <button type="button" class="btn btn-light ms-auto" onclick="window.print()"><i class="bi bi-printer me-1"></i> Print</button>
        @error('to') <div class="text-danger small w-100">{{ $message }}</div> @enderror
    </form>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3"><x-stat-tile label="Liters dispensed" :value="\App\Support\Format::liters($report['totals']['liters'])" icon="droplet-fill" tone="fuel" /></div>
        <div class="col-sm-6 col-xl-3"><x-stat-tile label="Sales from nozzles" :value="\App\Support\Format::money(round($report['totals']['amount']))" icon="cash-stack" tone="sales" /></div>
        <div class="col-sm-6 col-xl-3"><x-stat-tile label="Top nozzle" :value="$rows->first() && $rows->first()['liters'] > 0 ? $rows->first()['nozzle']->label() : '—'" icon="trophy" tone="cash" :hint="$rows->first() ? \App\Support\Format::liters($rows->first()['liters']) : null" /></div>
        <div class="col-sm-6 col-xl-3"><x-stat-tile label="Average per day" :value="\App\Support\Format::liters(count($report['days']) ? round($report['totals']['liters'] / count($report['days']), 2) : 0)" icon="calendar3" tone="teal" /></div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-5">
            <div class="card h-100">
                <div class="card-header">Liters by nozzle</div>
                <div class="card-body">
                    <div class="bar-3d">
                        @foreach ($rows as $row)
                            <div class="d-flex flex-column align-items-center flex-fill h-100 justify-content-end {{ \App\Support\Grade::cssClass($row['nozzle']->fuelProduct) }}" title="{{ $row['nozzle']->label() }}: {{ \App\Support\Format::liters($row['liters']) }}">
                                <div class="small fw-bold ft-num mb-2">{{ \App\Support\Format::number(round($row['liters'])) }}</div>
                                <div class="bar w-100" style="height: {{ max(2, round($row['liters'] / $maxLiters * 82)) }}%"></div>
                            </div>
                        @endforeach
                    </div>
                    <div class="d-flex gap-2 px-2 mt-2">
                        @foreach ($rows as $row)
                            <div class="flex-fill text-center small text-secondary text-truncate">{{ str($row['nozzle']->pump->name)->after('Pump ') }}·{{ $row['nozzle']->number }}</div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-7">
            <div class="card h-100">
                <div class="card-header">Ranking</div>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr><th>Nozzle</th><th class="text-end">Liters</th><th class="text-end">Sales</th><th class="text-end">Avg / day</th><th>Share</th><th class="d-none d-md-table-cell">Best day</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr class="{{ \App\Support\Grade::cssClass($row['nozzle']->fuelProduct) }}">
                                    <td><span class="d-flex align-items-center gap-2"><span class="grade-dot"></span><span><span class="fw-semibold d-block">{{ $row['nozzle']->label() }}</span><span class="small text-secondary">{{ $row['nozzle']->fuelProduct->name }} · {{ $row['readings'] }} readings</span></span></span></td>
                                    <td class="text-end ft-num fw-bold">@liters($row['liters'])</td>
                                    <td class="text-end ft-num">@money(round($row['amount']))</td>
                                    <td class="text-end ft-num">@liters($row['avg_per_day'])</td>
                                    <td style="min-width: 110px;"><div class="share-bar"><span style="width: {{ $row['share'] }}%"></span></div><div class="small text-secondary">{{ $row['share'] }}%</div></td>
                                    <td class="d-none d-md-table-cell small">{{ $row['best_day'] ? $row['best_day']->format('D d M').' · '.\App\Support\Format::liters($row['best_liters']) : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Daily liters per nozzle</div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Nozzle</th>
                        @foreach ($report['days'] as $day)
                            <th class="text-end">{{ $day->format('d M') }}<div class="fw-normal">{{ $day->format('D') }}</div></th>
                        @endforeach
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr class="{{ \App\Support\Grade::cssClass($row['nozzle']->fuelProduct) }}">
                            <td class="text-nowrap"><span class="d-flex align-items-center gap-2"><span class="grade-dot"></span>{{ $row['nozzle']->label() }}</span></td>
                            @foreach ($row['daily'] as $liters)
                                <td class="heat-cell" style="--heat: {{ $report['max_cell'] > 0 ? round($liters / $report['max_cell'], 2) : 0 }}">{{ $liters > 0 ? \App\Support\Format::number(round($liters)) : '—' }}</td>
                            @endforeach
                            <td class="text-end ft-num fw-bold">{{ \App\Support\Format::number(round($row['liters'])) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td>All nozzles</td>
                        @foreach ($report['daily_totals'] as $liters)
                            <td class="text-end ft-num">{{ \App\Support\Format::number(round($liters)) }}</td>
                        @endforeach
                        <td class="text-end ft-num">{{ \App\Support\Format::number(round($report['totals']['liters'])) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</x-layouts.app>
