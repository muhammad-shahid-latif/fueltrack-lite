<x-layouts.app title="Dashboard" :subtitle="\App\Support\Station::name().', '.now()->format('l d F Y')">
    @php
        $lowStock = $stock->where('is_low', true);
        $fuelStock = $stock->filter(fn ($row) => $row['product']->isMetered());
        $itemStock = $stock->reject(fn ($row) => $row['product']->isMetered());
    @endphp

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <h2 class="h4 fw-bold mb-0">Welcome back, {{ auth()->user()->name }}</h2>
        <div class="d-flex flex-wrap gap-2">
            @if ($todayClosing)
                <a href="{{ route('closings.show', $todayClosing) }}" class="btn btn-light"><i class="bi bi-lock-fill me-1"></i> Day closed</a>
            @else
                <a href="{{ route('meter-readings.create') }}" class="btn btn-warning"><i class="bi bi-plus-lg me-1"></i> New reading</a>
                <a href="{{ route('sales.create') }}" class="btn btn-light"><i class="bi bi-bag-plus me-1"></i> Counter sale</a>
            @endif
            <a href="{{ route('reports.daily') }}" class="btn btn-light"><i class="bi bi-file-earmark-text me-1"></i> Today's report</a>
        </div>
    </div>

    <div class="mb-4">
        <x-price-board :products="$priceBoard" />
    </div>

    @if ($lowStock->isNotEmpty())
        <div class="alert alert-danger d-flex align-items-center gap-2">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div>Running low: <strong>{{ $lowStock->map(fn ($row) => $row['product']->name)->join(', ') }}</strong>. <a href="{{ route('purchases.create') }}" class="alert-link">Record a purchase</a></div>
        </div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <x-stat-tile label="Today's sales" :value="\App\Support\Format::money(round($today['total_sales']))" icon="cash-stack" tone="sales"
                         :hint="$today['counter_sales'] > 0 ? 'Incl. '.\App\Support\Format::money(round($today['counter_sales'])).' counter items' : null" />
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <x-stat-tile label="Fuel sold" :value="\App\Support\Format::liters($today['total_liters'])" icon="droplet-fill" tone="fuel" />
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <x-stat-tile label="Expenses" :value="\App\Support\Format::money(round($today['total_expenses']))" icon="wallet2" tone="expense" />
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <x-stat-tile :label="$todayClosing ? 'Cash counted' : 'Cash in drawer (est.)'" :value="\App\Support\Format::money(round($cashInHand))" icon="safe2" tone="cash"
                         :hint="$todayClosing ? 'From today\'s closing' : 'Before card and credit split'" />
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-8">
            @include('dashboard.partials.sales-chart', ['days' => $week])
        </div>
        <div class="col-xl-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    Sold today
                    <a href="{{ route('sales.index', ['from' => today()->toDateString(), 'to' => today()->toDateString()]) }}" class="small fw-normal text-decoration-none">All sales</a>
                </div>
                <ul class="list-group list-group-flush">
                    @foreach ($products as $row)
                        <li class="list-group-item d-flex align-items-center gap-3 px-4 py-3 {{ \App\Support\Grade::cssClass($row['product']) }}">
                            <span class="grade-dot"></span>
                            <span class="flex-grow-1">
                                <span class="d-block fw-semibold">{{ $row['product']->name }}</span>
                                <span class="small text-secondary">@qty($row['quantity'], $row['product']->unit)</span>
                            </span>
                            <span class="ft-num fw-bold">@money($row['amount'])</span>
                        </li>
                    @endforeach
                    <li class="list-group-item d-flex justify-content-between px-4 py-3 bg-body-tertiary rounded-bottom-4">
                        <span class="fw-bold">Total</span>
                        <span class="ft-num fw-bold">@money($today['total_sales'])</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-7">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    Stock
                    <a href="{{ route('stock.index') }}" class="small fw-normal text-decoration-none">Details</a>
                </div>
                <div class="card-body">
                    @foreach ($fuelStock as $row)
                        @php($capacity = max($row['closing'], (float) $row['product']->low_stock_threshold * 4, 1))
                        <div class="mb-3 {{ \App\Support\Grade::cssClass($row['product']) }}">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="d-flex align-items-center gap-2 fw-semibold"><span class="grade-dot"></span>{{ $row['product']->name }}</span>
                                <span class="ft-num fw-bold">
                                    @liters($row['closing'])
                                    @if ($row['is_low'])
                                        <span class="badge text-bg-danger ms-1">Low</span>
                                    @endif
                                </span>
                            </div>
                            <div class="progress" style="height: 10px; border-radius: 99px;" role="progressbar" aria-label="{{ $row['product']->name }} stock">
                                <div class="progress-bar" style="width: {{ min(100, round($row['closing'] / $capacity * 100)) }}%; background: var(--grade); border-radius: 99px;"></div>
                            </div>
                        </div>
                    @endforeach

                    @if ($itemStock->isNotEmpty())
                        <div class="d-flex flex-wrap gap-2 pt-2 border-top mt-3">
                            @foreach ($itemStock as $row)
                                <span class="chip {{ \App\Support\Grade::cssClass($row['product']) }}">
                                    <span class="grade-dot"></span>{{ $row['product']->name }}
                                    <span @class(['ft-num', 'text-danger' => $row['is_low']])>@qty($row['closing'], $row['product']->unit)</span>
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    Recent closings
                    <a href="{{ route('closings.index') }}" class="small fw-normal text-decoration-none">All</a>
                </div>
                <ul class="list-group list-group-flush">
                    @forelse ($recentClosings as $closing)
                        <a href="{{ route('closings.show', $closing) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center px-4 py-3">
                            <span>
                                <span class="fw-semibold d-block">{{ $closing->business_date->format('D, d M') }}</span>
                                <span class="small text-secondary ft-num">@money($closing->total_sales)</span>
                            </span>
                            @if ($closing->hasCashDifference())
                                <span class="badge text-bg-danger"><i class="bi bi-exclamation-triangle me-1"></i>{{ $closing->difference > 0 ? '+' : '' }}@money($closing->difference)</span>
                            @else
                                <span class="badge text-bg-success"><i class="bi bi-check-lg me-1"></i>Balanced</span>
                            @endif
                        </a>
                    @empty
                        <li class="list-group-item empty-state">No closings yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</x-layouts.app>
