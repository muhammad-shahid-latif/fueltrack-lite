@php
    $isSingleDay = $report['from']->isSameDay($report['to']);
    $period = $isSingleDay
        ? $report['from']->format('d F Y')
        : $report['from']->format('d M Y').' – '.$report['to']->format('d M Y');
@endphp

<x-layouts.app title="Daily report" subtitle="Print it or save it as a PDF">
    <form method="GET" action="{{ route('reports.daily') }}" class="card mb-4 d-print-none">
        <div class="card-body row g-2 align-items-end">
            <div class="col-6 col-md-3">
                <label for="from" class="form-label small mb-1">From date</label>
                <input type="date" id="from" name="from" value="{{ $report['from']->toDateString() }}" max="{{ today()->toDateString() }}" class="form-control">
            </div>
            <div class="col-6 col-md-3">
                <label for="to" class="form-label small mb-1">To date</label>
                <input type="date" id="to" name="to" value="{{ $report['to']->toDateString() }}" max="{{ today()->toDateString() }}" class="form-control">
            </div>
            <div class="col-md-6 d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-dark"><i class="bi bi-search me-1"></i> View report</button>
                <a href="{{ route('reports.daily', ['from' => today()->subDays(6)->toDateString(), 'to' => today()->toDateString()]) }}" class="btn btn-outline-secondary">Last 7 days</a>
                <button type="button" class="btn btn-warning ms-md-auto" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i> Print / Save PDF
                </button>
            </div>
        </div>
    </form>

    @error('to') <div class="alert alert-danger d-print-none">{{ $message }}</div> @enderror
    @error('from') <div class="alert alert-danger d-print-none">{{ $message }}</div> @enderror

    <div class="report-sheet card">
        <div class="card-body p-4 p-md-5">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 border-bottom pb-3 mb-4">
                <div>
                    <h2 class="h4 fw-bold mb-0">{{ \App\Support\Station::name() }}</h2>
                    <div class="text-secondary small">
                        {{ \App\Support\Station::address() }}@if (\App\Support\Station::phone()) · {{ \App\Support\Station::phone() }}@endif
                    </div>
                </div>
                <div class="text-end">
                    <div class="fw-bold">{{ $isSingleDay ? 'Daily Sales Report' : 'Sales Report' }}</div>
                    <div class="text-secondary">{{ $period }}</div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="border rounded-3 p-3 h-100">
                        <div class="small text-secondary">Total sales</div>
                        <div class="fs-5 fw-bold">@money($report['total_sales'])</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="border rounded-3 p-3 h-100">
                        <div class="small text-secondary">Fuel sold</div>
                        <div class="fs-5 fw-bold">@liters($report['total_liters'])</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="border rounded-3 p-3 h-100">
                        <div class="small text-secondary">Expenses</div>
                        <div class="fs-5 fw-bold">@money($report['total_expenses'])</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="border rounded-3 p-3 h-100 bg-body-tertiary">
                        <div class="small text-secondary">Net (sales − expenses)</div>
                        <div class="fs-5 fw-bold">@money($report['net'])</div>
                    </div>
                </div>
            </div>

            <h3 class="h6 fw-bold mb-2">Sales by product</h3>
            <table class="table table-sm align-middle mb-4">
                <thead>
                    <tr><th>Product</th><th class="text-end">Quantity</th><th class="text-end">Sales</th></tr>
                </thead>
                <tbody>
                    @foreach ($report['products'] as $row)
                        <tr>
                            <td>{{ $row['product']->name }}</td>
                            <td class="text-end ft-num">@qty($row['quantity'], $row['product']->unit)</td>
                            <td class="text-end ft-num">@money($row['amount'])</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="fw-bold">
                        <td>Total</td>
                        <td class="text-end ft-num">@liters($report['total_liters']) fuel</td>
                        <td class="text-end ft-num">@money($report['total_sales'])</td>
                    </tr>
                </tfoot>
            </table>

            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <h3 class="h6 fw-bold mb-2">Expenses</h3>
                    <table class="table table-sm mb-0">
                        <tbody>
                            @forelse ($report['expenses_by_category'] as $row)
                                <tr><td>{{ $row['category']->label() }}</td><td class="text-end">@money($row['amount'])</td></tr>
                            @empty
                                <tr><td class="text-secondary" colspan="2">No expenses.</td></tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="fw-bold"><td>Total expenses</td><td class="text-end">@money($report['total_expenses'])</td></tr>
                        </tfoot>
                    </table>
                </div>
                <div class="col-md-6">
                    <h3 class="h6 fw-bold mb-2">Net</h3>
                    <table class="table table-sm mb-0">
                        <tr><td>Sales</td><td class="text-end">@money($report['total_sales'])</td></tr>
                        <tr><td>Expenses</td><td class="text-end">@money(-$report['total_expenses'])</td></tr>
                        <tr class="fw-bold"><td>Net</td><td class="text-end">@money($report['net'])</td></tr>
                        <tr><td class="text-secondary">Stock purchased</td><td class="text-end text-secondary ft-num">@money($report['purchases']['amount'])</td></tr>
                        <tr @class(['table-danger' => $report['cash_difference'] != 0])>
                            <td>Cash difference (closed days)</td>
                            <td class="text-end">{{ $report['cash_difference'] > 0 ? '+' : '' }}@money($report['cash_difference'])</td>
                        </tr>
                    </table>
                </div>
            </div>

            @unless ($isSingleDay)
                <h3 class="h6 fw-bold mb-2">Day by day</h3>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-4">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th class="text-end">Liters</th>
                                <th class="text-end">Sales</th>
                                <th class="text-end">Expenses</th>
                                <th class="text-end">Net</th>
                                <th class="text-end">Cash diff.</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($report['days'] as $day)
                                <tr>
                                    <td class="text-nowrap">{{ $day['date']->format('D, d M') }}</td>
                                    <td class="text-end">@liters($day['liters'])</td>
                                    <td class="text-end">@money($day['sales'])</td>
                                    <td class="text-end">@money($day['expenses'])</td>
                                    <td class="text-end fw-semibold">@money($day['net'])</td>
                                    <td class="text-end">
                                        @if (! $day['closing'])
                                            <span class="text-secondary small">Open</span>
                                        @elseif ($day['closing']->hasCashDifference())
                                            <span class="text-danger">{{ $day['closing']->difference > 0 ? '+' : '' }}@money($day['closing']->difference)</span>
                                        @else
                                            <span class="text-success">0</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endunless

            <div class="d-flex justify-content-between small text-secondary border-top pt-2">
                <span>Generated {{ now()->format('d M Y, h:i A') }} by {{ auth()->user()->name }}</span>
                <span>FuelTrack</span>
            </div>
        </div>
    </div>
</x-layouts.app>
