<x-layouts.app title="Nozzle meters" subtitle="Meter and today's sales of every nozzle, from the readings entered by staff">
    @php($shiftPlan = \App\Support\ShiftSchedule::shifts())

    <div x-data="nozzleBoard(@js(route('nozzles.live')), @js(now()->format('h:i:s A')))">
        <section class="hero-3d mb-4" data-tilt="1.2">
            <div class="row g-3 align-items-center">
                <div class="col-lg-4">
                    <div class="mb-2"><span class="record-chip"><i class="bi bi-journal-check"></i>From meter readings</span></div>
                    <div class="hero-label mb-1">{{ $businessDate->format('l d M') }} · refreshed <span x-text="at"></span></div>
                    <div class="hero-value">@liters($board->sum('liters'))</div>
                    <div class="hero-sub">sold today from {{ $board->where('readings', '>', 0)->count() }} of {{ $board->count() }} nozzles</div>
                </div>
                <div class="col-sm-6 col-lg-4">
                    <div class="hero-tile">
                        <div class="hero-label">Sales from readings</div>
                        <div class="hero-value is-white">@money($board->sum('amount'))</div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-4 text-lg-end">
                    <a href="{{ route('meter-readings.create') }}" class="btn btn-warning btn-lg"><i class="bi bi-plus-lg me-1"></i> New reading</a>
                    <a href="{{ route('reports.nozzles') }}" class="btn btn-light btn-lg ms-1"><i class="bi bi-bar-chart-line"></i></a>
                </div>
            </div>
        </section>

        <div class="row g-4">
            @foreach ($board as $row)
                @php($nozzle = $row['nozzle'])
                <div class="col-sm-6 col-xl-4 col-xxl-3">
                    <div @class(['dispenser-3d h-100', \App\Support\Grade::cssClass($nozzle->fuelProduct), 'opacity-50' => ! $nozzle->is_active]) data-tilt="5">
                        <div class="dispenser-cap d-flex justify-content-between align-items-center">
                            <div>
                                <div class="nozzle-no">{{ $nozzle->pump->name }} · N{{ $nozzle->number }}</div>
                                <div class="small opacity-75">{{ $nozzle->fuelProduct->name }}{{ $nozzle->tank ? ' · '.$nozzle->tank->name : '' }}</div>
                            </div>
                            <i class="bi bi-fuel-pump-fill fs-3 tilt-pop"></i>
                        </div>
                        <div class="p-3">
                            <div class="led-screen mb-3 tilt-pop">
                                <div class="led-label">Meter</div>
                                <div class="led-value" data-nozzle-meter="{{ $nozzle->id }}">{{ \App\Support\Format::meter($nozzle->current_meter) }}</div>
                            </div>
                            <div class="d-flex justify-content-between align-items-end mb-2">
                                <div>
                                    <div class="small text-secondary">Today</div>
                                    <div class="fw-bold ft-num fs-5" data-nozzle-liters="{{ $nozzle->id }}">@liters($row['liters'])</div>
                                </div>
                                <div class="text-end">
                                    <div class="small text-secondary">Sales</div>
                                    <div class="fw-bold ft-num" data-nozzle-amount="{{ $nozzle->id }}">@money($row['amount'])</div>
                                </div>
                            </div>
                            <div class="share-bar mb-3" title="Share of today's liters"><span style="width: {{ $row['share'] }}%" data-nozzle-share="{{ $nozzle->id }}"></span></div>
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="shift-pips" data-nozzle-shifts="{{ $nozzle->id }}">
                                    @foreach ($shiftPlan as $plan)
                                        <span @class(['shift-pip', 'is-done' => in_array($plan['shift']->value, $row['shifts'], true)]) data-shift="{{ $plan['shift']->value }}" title="{{ $plan['name'] }} shift · {{ $plan['hours'] }}">{{ mb_strtoupper(mb_substr($plan['name'], 0, 1)) }}</span>
                                    @endforeach
                                </div>
                                @if ($nozzle->is_active)
                                    <a href="{{ route('meter-readings.create', ['nozzle' => $nozzle->id]) }}" class="btn btn-sm btn-dark"><i class="bi bi-pencil-square me-1"></i> Reading</a>
                                @else
                                    <span class="badge text-bg-secondary">Inactive</span>
                                @endif
                            </div>
                            <div class="small text-secondary mt-2" data-nozzle-last="{{ $nozzle->id }}">
                                {{ $row['last_at'] ? 'Last reading '.$row['last_at']->format('h:i A').($row['last_by'] ? ' by '.$row['last_by'] : '') : 'No reading yet today' }}
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <script>
        function nozzleBoard(url, at) {
            return {
                at,
                timer: null,

                init() {
                    this.timer = setInterval(() => document.hidden || this.refresh(url), 15000);
                },

                // Alpine calls this when Turbo swaps the page away.
                destroy() {
                    clearInterval(this.timer);
                },

                async refresh(url) {
                    try {
                        const data = await FT.http.get(url);
                        this.at = data.at;

                        data.nozzles.forEach((n) => {
                            const set = (attr, value) => document.querySelectorAll(`[${attr}="${n.id}"]`).forEach((el) => (el.textContent = value));
                            set('data-nozzle-meter', n.meter);
                            set('data-nozzle-liters', n.liters);
                            set('data-nozzle-amount', n.amount);
                            set('data-nozzle-last', n.last ? `Last reading ${n.last}` : 'No reading yet today');
                            document.querySelectorAll(`[data-nozzle-share="${n.id}"]`).forEach((el) => (el.style.width = `${n.share}%`));
                            document.querySelectorAll(`[data-nozzle-shifts="${n.id}"] [data-shift]`).forEach((el) => el.classList.toggle('is-done', n.shifts.includes(el.dataset.shift)));
                        });
                    } catch (error) {
                        // Keep the last figures on a network hiccup.
                    }
                },
            };
        }
    </script>
</x-layouts.app>
