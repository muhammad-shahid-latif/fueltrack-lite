<x-layouts.app title="Tanks" subtitle="Live fuel levels, worked out from deliveries, nozzle sales and dips">
    @php($statusBadge = ['ok' => ['text-bg-success', 'Healthy'], 'low' => ['text-bg-warning', 'Low'], 'critical' => ['text-bg-danger', 'Critical']])

    <div x-data="tankBoard(@js(route('tanks.levels')), @js(now()->format('h:i:s A')))">
    <section class="hero-3d mb-4" data-tilt="1.2">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
            <div class="d-flex align-items-center gap-2">
                <span class="live-dot"></span>
                <span class="hero-label">Live tank levels · updated <span x-text="at"></span></span>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('stock.movements') }}" class="btn btn-light btn-sm"><i class="bi bi-arrow-left-right me-1"></i> Inventory movements</a>
                <a href="{{ route('purchases.create') }}" class="btn btn-warning btn-sm"><i class="bi bi-truck me-1"></i> Receive delivery</a>
                @if (auth()->user()->isOwner())
                    <a href="{{ route('tanks.create') }}" class="btn btn-light btn-sm"><i class="bi bi-plus-lg me-1"></i> Add tank</a>
                @endif
            </div>
        </div>

        <div class="row g-3">
            @forelse ($products as $row)
                @php($pct = $row['capacity'] > 0 ? round(max(0, $row['level']) / $row['capacity'] * 100) : 0)
                <div class="col-sm-6 col-xl">
                    <div class="hero-tile {{ \App\Support\Grade::cssClass($row['product']) }}">
                        <div class="d-flex align-items-center gap-2 mb-1 fw-semibold"><span class="grade-dot"></span>{{ $row['product']->name }}</div>
                        <div class="hero-value">@liters($row['level'])</div>
                        <div class="hero-sub mt-1">{{ $pct }}% of @liters($row['capacity']) · avg cost
                            {{ $row['average_cost'] !== null ? \App\Support\Format::money(round($row['average_cost'], 2)).'/L' : '—' }}</div>
                    </div>
                </div>
            @empty
                <div class="col-12 hero-sub">No tanks yet.</div>
            @endforelse
        </div>
    </section>

    @if ($levels->isEmpty())
        <div class="card"><div class="card-body empty-state">
            <i class="bi bi-database"></i>
            No tanks set up yet.
            @if (auth()->user()->isOwner())
                <div class="mt-3"><a href="{{ route('tanks.create') }}" class="btn btn-warning">Add the first tank</a></div>
            @endif
        </div></div>
    @endif

    <div class="row g-4">
        @foreach ($levels as $row)
            @php($tank = $row['tank'])
            <div class="col-md-6 col-xl-4 col-xxl-3">
                <div @class(['card tank-card h-100', 'is-critical' => $row['status'] === 'critical', 'is-inactive' => ! $tank->is_active]) data-tilt="4">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                            <div>
                                <a href="{{ route('tanks.show', $tank) }}" class="tank-name text-decoration-none text-reset stretched-link">{{ $tank->name }}</a>
                                <div class="small text-secondary d-flex align-items-center gap-2 {{ \App\Support\Grade::cssClass($tank->fuelProduct) }}">
                                    <span class="grade-dot"></span>{{ $tank->fuelProduct->name }} · {{ $tank->nozzles_count }} {{ str('nozzle')->plural($tank->nozzles_count) }}
                                </div>
                            </div>
                            @if ($tank->is_active)
                                <span class="badge {{ $statusBadge[$row['status']][0] }}" data-tank-status="{{ $tank->id }}">{{ $statusBadge[$row['status']][1] }}</span>
                            @else
                                <span class="badge text-bg-secondary">Inactive</span>
                            @endif
                        </div>

                        <x-tank-3d :row="$row" />

                        <dl class="row tank-meta mb-0 mt-2 text-center">
                            <div class="col-6"><dt>In tank</dt><dd data-tank-level="{{ $tank->id }}">@liters($row['level'])</dd></div>
                            <div class="col-6"><dt>Room left</dt><dd data-tank-ullage="{{ $tank->id }}">@liters($row['ullage'])</dd></div>
                            <div class="col-6"><dt>Sold today</dt><dd class="amount-out" data-tank-sold="{{ $tank->id }}">@liters($row['sold_today'])</dd></div>
                            <div class="col-6"><dt>Days of cover</dt><dd>{{ $row['days_cover'] !== null ? number_format($row['days_cover'], 1).' days' : '—' }}</dd></div>
                        </dl>

                        <div class="small text-secondary text-center mt-1">
                            @if ($row['last_dip'])
                                <i class="bi bi-rulers me-1"></i>Last dip {{ $row['last_dip']->business_date->format('d M') }}:
                                <span @class(['fw-semibold', 'amount-in' => $row['last_dip']->quantity > 0, 'amount-out' => $row['last_dip']->quantity < 0])>
                                    {{ $row['last_dip']->quantity > 0 ? '+' : '' }}{{ \App\Support\Format::liters($row['last_dip']->quantity) }}
                                </span>
                            @else
                                <i class="bi bi-rulers me-1"></i>No dip recorded yet
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    </div>

    <script>
        // Re-reads every tank level every 20 seconds so the page stays live.
        function tankBoard(url, at) {
            return {
                at,

                init() {
                    setInterval(() => this.refresh(url), 20000);
                },

                async refresh(url) {
                    try {
                        const response = await fetch(url, { headers: { Accept: 'application/json' } });
                        if (!response.ok) return;
                        const data = await response.json();
                        this.at = data.at;
                        const labels = { ok: ['text-bg-success', 'Healthy'], low: ['text-bg-warning', 'Low'], critical: ['text-bg-danger', 'Critical'] };

                        data.tanks.forEach((tank) => {
                            const set = (attr, value) => document.querySelectorAll(`[${attr}="${tank.id}"]`).forEach((el) => (el.textContent = value));
                            document.querySelectorAll(`[data-tank-fill="${tank.id}"]`).forEach((el) => el.style.setProperty('--fill', `${tank.percent}%`));
                            set('data-tank-percent', `${Math.round(tank.percent)}%`);
                            set('data-tank-level', tank.level_label);
                            set('data-tank-ullage', tank.ullage_label);
                            set('data-tank-sold', tank.sold_today_label);
                            document.querySelectorAll(`[data-tank-status="${tank.id}"]`).forEach((el) => {
                                el.className = `badge ${labels[tank.status][0]}`;
                                el.textContent = labels[tank.status][1];
                            });
                        });
                    } catch (error) {
                        // Network hiccup: keep showing the last figures.
                    }
                },
            };
        }
    </script>
</x-layouts.app>
