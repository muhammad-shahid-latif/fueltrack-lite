@use('App\Support\Format')

@php
    $isProfit = $report['net_profit'] >= 0;
    $period = $report['from']->format('d M Y').' – '.$report['to']->format('d M Y');
    $maxMonthly = max(1, $monthly->max(fn ($row) => abs($row['net_profit'])));
@endphp

<x-layouts.app title="Profit & loss" subtitle="Sales minus cost of goods, running expenses and salaries">
    <form method="GET" action="{{ route('reports.profit-loss') }}" class="card mb-4 d-print-none">
        <div class="card-body row g-2 align-items-end">
            <div class="col-6 col-md-3 col-xl-2">
                <label for="from" class="form-label small mb-1">From</label>
                <input type="date" id="from" name="from" value="{{ $report['from']->toDateString() }}" max="{{ today()->toDateString() }}" class="form-control">
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <label for="to" class="form-label small mb-1">To</label>
                <input type="date" id="to" name="to" value="{{ $report['to']->toDateString() }}" max="{{ today()->toDateString() }}" class="form-control">
            </div>
            <div class="col-md-6 col-xl-8 d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-dark"><i class="bi bi-search me-1"></i> View</button>
                @foreach ($presets as $label => [$presetFrom, $presetTo])
                    @php($active = $presetFrom->isSameDay($report['from']) && $presetTo->isSameDay($report['to']))
                    <a href="{{ route('reports.profit-loss', ['from' => $presetFrom->toDateString(), 'to' => $presetTo->toDateString()]) }}"
                       @class(['btn', 'btn-outline-secondary' => ! $active, 'btn-secondary' => $active])>{{ $label }}</a>
                @endforeach
                <button type="button" class="btn btn-warning ms-xl-auto" onclick="window.print()"><i class="bi bi-printer me-1"></i> Print</button>
            </div>
        </div>
    </form>

    @error('to') <div class="alert alert-danger d-print-none">{{ $message }}</div> @enderror

    @if ($report['missing_costs']->isNotEmpty())
        <div class="alert alert-warning d-print-none">
            <i class="bi bi-exclamation-triangle me-1"></i>
            No cost price for <strong>{{ $report['missing_costs']->pluck('name')->join(', ') }}</strong>, so their cost is counted as zero.
            Set a cost price on the product page or record a purchase.
        </div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-lg-5">
            <div @class(['pl-result h-100 d-flex flex-column', 'is-loss' => ! $isProfit])>
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <span class="fw-semibold"><i class="bi bi-{{ $isProfit ? 'graph-up-arrow' : 'graph-down-arrow' }} me-1"></i>{{ $isProfit ? 'Net profit' : 'Net loss' }}</span>
                    @if ($report['margin'] !== null)
                        <span class="badge bg-white text-dark">{{ $report['margin'] }}% of sales</span>
                    @endif
                </div>
                <div class="pl-amount my-3">{{ Format::money(round(abs($report['net_profit']))) }}</div>
                <div class="small opacity-75 mt-auto">{{ $period }}</div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="row g-3 h-100">
                <div class="col-sm-6">
                    <x-stat-tile label="Sales" :value="Format::money(round($report['total_sales']))" icon="cash-stack" tone="sales" />
                </div>
                <div class="col-sm-6">
                    <x-stat-tile label="Gross profit" :value="Format::money(round($report['gross_profit']))" icon="droplet-half" tone="fuel" hint="Sales minus cost of goods sold" />
                </div>
                <div class="col-sm-6">
                    <x-stat-tile label="Running expenses" :value="Format::money(round($report['total_operating']))" icon="wallet2" tone="expense" />
                </div>
                <div class="col-sm-6">
                    <x-stat-tile label="Staff salaries" :value="Format::money(round($report['total_payroll']))" icon="people-fill" tone="violet" hint="Salaries and advances paid" />
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-6">
            <div class="report-sheet card">
                <div class="card-header d-flex justify-content-between">
                    <span>Statement</span>
                    <span class="fw-normal text-secondary small">{{ $period }}</span>
                </div>
                <div class="table-responsive">
                    <table class="table pl-table align-middle mb-0">
                        <tbody>
                            <tr class="pl-section"><td colspan="2">Sales</td></tr>
                            <tr><td class="ps-4">Fuel</td><td class="text-end ft-num">@money($report['fuel_sales'])</td></tr>
                            <tr><td class="ps-4">Oil and counter items</td><td class="text-end ft-num">@money($report['counter_sales'])</td></tr>
                            <tr class="fw-semibold"><td>Total sales</td><td class="text-end ft-num">@money($report['total_sales'])</td></tr>
                            <tr><td>− Cost of goods sold</td><td class="text-end ft-num text-danger">@money(-$report['cost_of_sales'])</td></tr>
                            <tr class="pl-subtotal"><td>Gross profit</td><td class="text-end ft-num">@money($report['gross_profit'])</td></tr>

                            <tr class="pl-section"><td colspan="2">Running expenses</td></tr>
                            @forelse ($report['operating_expenses'] as $row)
                                <tr><td class="ps-4">{{ $row['category']->label() }}</td><td class="text-end ft-num">@money($row['amount'])</td></tr>
                            @empty
                                <tr><td class="ps-4 text-secondary" colspan="2">None</td></tr>
                            @endforelse
                            <tr class="fw-semibold"><td>Total running expenses</td><td class="text-end ft-num text-danger">@money(-$report['total_operating'])</td></tr>

                            <tr class="pl-section"><td colspan="2">Staff</td></tr>
                            <tr><td class="ps-4">Salaries paid</td><td class="text-end ft-num">@money($report['salaries'])</td></tr>
                            <tr><td class="ps-4">Advances paid</td><td class="text-end ft-num">@money($report['advances'])</td></tr>
                            <tr class="fw-semibold"><td>Total staff cost</td><td class="text-end ft-num text-danger">@money(-$report['total_payroll'])</td></tr>

                            <tr>
                                <td>{{ $report['cash_difference'] < 0 ? 'Cash short' : 'Cash excess' }} at closings</td>
                                <td @class(['text-end ft-num', 'text-danger' => $report['cash_difference'] < 0])>@money($report['cash_difference'])</td>
                            </tr>

                            <tr @class(['pl-subtotal fs-5', 'table-success' => $isProfit, 'table-danger' => ! $isProfit])>
                                <td>{{ $isProfit ? 'Net profit' : 'Net loss' }}</td>
                                <td class="text-end ft-num">@money($report['net_profit'])</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card mb-4">
                <div class="card-header">Profit by product</div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th class="text-end">Sold</th>
                                <th class="text-end d-none d-sm-table-cell">Avg cost</th>
                                <th class="text-end">Gross profit</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($report['products'] as $row)
                                <tr>
                                    <td>
                                        <span class="d-flex align-items-center gap-2 {{ \App\Support\Grade::cssClass($row['product']) }}">
                                            <span class="grade-dot"></span>
                                            <span>
                                                <span class="fw-semibold d-block">{{ $row['product']->name }}</span>
                                                <span class="small text-secondary text-nowrap">@money($row['sales']) sales</span>
                                            </span>
                                        </span>
                                    </td>
                                    <td class="text-end ft-num text-nowrap">@qty($row['quantity'], $row['product']->unit)</td>
                                    <td class="text-end ft-num d-none d-sm-table-cell text-nowrap">
                                        @if ($row['unit_cost'] === null)
                                            <span class="badge text-bg-warning">Not set</span>
                                        @else
                                            @money(round($row['unit_cost'], 2))
                                        @endif
                                    </td>
                                    <td class="text-end ft-num fw-bold text-nowrap">
                                        @money($row['gross'])
                                        @if ($row['sales'] > 0)
                                            <div class="small fw-normal text-secondary">{{ round($row['gross'] / $row['sales'] * 100, 1) }}%</div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="empty-state py-4"><i class="bi bi-receipt"></i>No sales in this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Last 6 months</div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @foreach ($monthly as $row)
                            <li class="list-group-item px-4 py-3">
                                <div class="d-flex justify-content-between align-items-baseline gap-2 mb-2">
                                    <a href="{{ route('reports.profit-loss', ['from' => $row['month']->toDateString(), 'to' => $row['month']->copy()->endOfMonth()->min(today())->toDateString()]) }}"
                                       class="fw-semibold text-reset text-decoration-none">{{ $row['month']->format('F Y') }}</a>
                                    <span @class(['ft-num fw-bold', 'text-success' => $row['net_profit'] >= 0, 'text-danger' => $row['net_profit'] < 0])>
                                        {{ $row['net_profit'] >= 0 ? '+' : '' }}@money(round($row['net_profit']))
                                    </span>
                                </div>
                                <div class="pl-bar mb-1">
                                    <span @class(['is-loss' => $row['net_profit'] < 0]) style="width: {{ round(abs($row['net_profit']) / $maxMonthly * 100, 1) }}%"></span>
                                </div>
                                <div class="small text-secondary">Sales @money(round($row['total_sales'])) · Expenses and salaries @money(round($row['expenses']))</div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <p class="small text-secondary mt-4 mb-0">
        Cost of goods sold uses each product's average cost: opening stock at its cost price plus every purchase up to the end of the period.
        Purchases themselves are stock, not expenses. Salaries and advances count on the day they are paid.
    </p>
</x-layouts.app>
