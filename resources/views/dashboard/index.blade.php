<x-layouts.app title="Dashboard">
    <div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-4">
        <div>
            <h2 class="h4 fw-bold mb-1">Welcome, {{ auth()->user()->name }}</h2>
            <p class="text-muted mb-0">{{ now()->format('l, d F Y') }} · {{ config('fueltrack.station.name') }}</p>
        </div>
    </div>

    @php
        $cards = [
            ['label' => "Today's Sales", 'icon' => 'cash-stack', 'tone' => 'success'],
            ['label' => 'Fuel Sold', 'icon' => 'droplet-fill', 'tone' => 'primary'],
            ['label' => 'Expenses', 'icon' => 'wallet2', 'tone' => 'danger'],
            ['label' => 'Cash in Hand', 'icon' => 'safe', 'tone' => 'warning'],
        ];
    @endphp

    <div class="row g-3 mb-4">
        @foreach ($cards as $card)
            <div class="col-6 col-xl-3">
                <div class="card stat-card border-0 shadow-sm h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="stat-icon bg-{{ $card['tone'] }}-subtle text-{{ $card['tone'] }}">
                            <i class="bi bi-{{ $card['icon'] }}"></i>
                        </div>
                        <div style="min-width: 0;">
                            <div class="small text-muted">{{ $card['label'] }}</div>
                            <div class="stat-value">—</div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5 text-muted">
            <i class="bi bi-bar-chart-line fs-1 d-block mb-2"></i>
            Sales, stock and expense figures appear here once the operations modules are added.
        </div>
    </div>
</x-layouts.app>
