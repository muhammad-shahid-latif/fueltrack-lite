<x-layouts.app title="Tanks" subtitle="Live fuel levels, worked out from deliveries, nozzle sales and dips">
    @php($statusBadge = ['ok' => ['text-bg-success', 'Healthy'], 'low' => ['text-bg-warning', 'Low'], 'critical' => ['text-bg-danger', 'Critical']])

    <div x-data="tankBoard({ levelsUrl: @js(route('tanks.levels')), at: @js(now()->format('h:i:s A')) })">
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
                <div @class(['card tank-card h-100', 'is-critical' => $row['status'] === 'critical', 'is-inactive' => ! $tank->is_active]) data-tilt="4" data-tank-card="{{ $tank->id }}">
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

                        <div class="tank-capacity-bar mt-2 mb-3">
                            <div class="d-flex justify-content-between align-items-baseline">
                                <span class="small text-secondary"><i class="bi bi-arrows-vertical me-1"></i>Full capacity</span>
                                <span class="ft-num fw-bold">@liters($row['capacity'])</span>
                            </div>
                            <div class="share-bar mt-1 {{ \App\Support\Grade::cssClass($tank->fuelProduct) }}"><span style="width: {{ $row['percent'] }}%" data-tank-bar="{{ $tank->id }}"></span></div>
                            <div class="small text-secondary mt-1 text-end"><span data-tank-level="{{ $tank->id }}">@liters($row['level'])</span> of @liters($row['capacity'])</div>
                        </div>

                        <dl class="row tank-meta mb-0 text-center">
                            <div class="col-6"><dt>In tank</dt><dd data-tank-level="{{ $tank->id }}">@liters($row['level'])</dd></div>
                            <div class="col-6"><dt>Room left</dt><dd data-tank-ullage="{{ $tank->id }}">@liters($row['ullage'])</dd></div>
                            <div class="col-6"><dt>Sold today</dt><dd class="amount-out" data-tank-sold="{{ $tank->id }}">@liters($row['sold_today'])</dd></div>
                            <div class="col-6"><dt>Days of cover</dt><dd data-tank-cover="{{ $tank->id }}">{{ $row['days_cover'] !== null ? number_format($row['days_cover'], 1).' days' : '—' }}</dd></div>
                        </dl>

                        <div class="tank-dip-line" data-tank-dip="{{ $tank->id }}">
                            @include('tanks.partials.last-dip', ['dip' => $row['last_dip']])
                        </div>

                        @if ($tank->is_active)
                            <button type="button" class="btn btn-warning w-100 mt-3 tank-dip-btn"
                                    @click="openDip(@js(route('tanks.dips.index', $tank)), @js(route('tanks.dips.store', $tank)))">
                                <i class="bi bi-rulers me-1"></i> Dip reading
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @include('tanks.partials.dip-modal')
    </div>

    <script>
        // Tank board: live levels every 20 seconds, plus the dip-reading popup.
        function tankBoard({ levelsUrl, at }) {
            const STATUS = { ok: ['text-bg-success', 'Healthy'], low: ['text-bg-warning', 'Low'], critical: ['text-bg-danger', 'Critical'] };
            const VERDICT = { match: 'is-match', gain: 'is-gain', loss: 'is-loss' };

            const blankDip = () => ({
                loading: false, saving: false, loadError: '', error: '', storeUrl: null,
                tank: null, book: null, bookLabel: '', at: '', dayClosed: false,
                history: [], measured: '', notes: '', result: null,
            });

            return {
                at,
                timer: null,
                dip: blankDip(),

                init() {
                    this.timer = setInterval(() => document.hidden || this.refresh(), 20000);
                },

                // Alpine calls this when Turbo swaps the page away.
                destroy() {
                    clearInterval(this.timer);
                },

                async refresh() {
                    try {
                        const data = await FT.http.get(levelsUrl);
                        this.at = data.at;
                        data.tanks.forEach((tank) => this.paint(tank));
                    } catch (error) {
                        // Network hiccup: keep showing the last figures.
                    }
                },

                /** Writes one tank's live figures into its card. */
                paint(tank) {
                    const each = (attr, fn) => document.querySelectorAll(`[${attr}="${tank.id}"]`).forEach(fn);
                    const text = (attr, value) => each(attr, (el) => (el.textContent = value));

                    each('data-tank-fill', (el) => el.style.setProperty('--fill', `${tank.percent}%`));
                    each('data-tank-bar', (el) => (el.style.width = `${tank.percent}%`));
                    text('data-tank-percent', `${Math.round(tank.percent)}%`);
                    text('data-tank-level', tank.level_label);
                    text('data-tank-ullage', tank.ullage_label);
                    text('data-tank-sold', tank.sold_today_label);
                    text('data-tank-cover', tank.days_cover_label);
                    each('data-tank-status', (el) => {
                        el.className = `badge ${STATUS[tank.status][0]}`;
                        el.textContent = STATUS[tank.status][1];
                    });
                    each('data-tank-dip', (el) => this.paintLastDip(el, tank.last_dip));
                },

                paintLastDip(el, dip) {
                    const icon = document.createElement('i');
                    icon.className = 'bi bi-rulers me-1';

                    if (!dip) {
                        el.replaceChildren(icon, 'No dip recorded yet');
                        return;
                    }

                    const figure = document.createElement('span');
                    figure.className = `fw-semibold ${dip.status === 'gain' ? 'amount-in' : dip.status === 'loss' ? 'amount-out' : 'text-success'}`;
                    figure.textContent = dip.status === 'match' ? 'Matched' : dip.variance_label;
                    el.replaceChildren(icon, `Last dip ${dip.date_label}${dip.time_label ? ', ' + dip.time_label : ''}: `, figure);
                },

                flash(id) {
                    const card = document.querySelector(`[data-tank-card="${id}"]`);
                    card?.classList.remove('just-updated');
                    void card?.offsetWidth;
                    card?.classList.add('just-updated');
                },

                // ---------- Dip popup ----------

                // The modal is teleported to <body>, so it is looked up by id rather than $refs.
                modal() {
                    return bootstrap.Modal.getOrCreateInstance(document.getElementById('dipModal'));
                },

                focusInput() {
                    document.getElementById('dipMeasured')?.focus();
                },

                async openDip(indexUrl, storeUrl) {
                    this.dip = { ...blankDip(), loading: true, storeUrl, indexUrl };
                    document.getElementById('dipModal').addEventListener('shown.bs.modal', () => this.focusInput(), { once: true });
                    this.modal().show();
                    await this.loadBook();
                },

                /** Fetches the system (book) stock at this moment. */
                async loadBook() {
                    this.dip.loading = true;
                    this.dip.loadError = '';

                    try {
                        const data = await FT.http.get(this.dip.indexUrl);
                        Object.assign(this.dip, {
                            tank: data.tank, book: data.book, bookLabel: data.book_label,
                            at: data.at, dayClosed: data.day_closed, history: data.history,
                        });
                        this.$nextTick(() => this.focusInput());
                    } catch (error) {
                        this.dip.loadError = error.message;
                    } finally {
                        this.dip.loading = false;
                    }
                },

                get measuredValue() {
                    const value = parseFloat(String(this.dip.measured).replace(/,/g, ''));
                    return Number.isFinite(value) && value >= 0 ? value : null;
                },

                /** Live comparison while typing: same rule as the server (2 decimals). */
                get preview() {
                    if (this.measuredValue === null || this.dip.book === null) return null;

                    const variance = Math.round((this.measuredValue - this.dip.book) * 100) / 100;
                    const status = Math.abs(variance) < 0.005 ? 'match' : variance > 0 ? 'gain' : 'loss';
                    const percent = this.dip.book > 0 ? (variance / this.dip.book) * 100 : null;

                    return { variance, status, percent, overCapacity: this.dip.tank && this.measuredValue > this.dip.tank.capacity };
                },

                get canSave() {
                    return !this.dip.loading && !this.dip.saving && !this.dip.dayClosed && this.preview !== null && !this.preview.overCapacity;
                },

                // Same style as Format::liters(): whole liters plain, otherwise two decimals.
                liters(value, signed = false) {
                    const digits = Number.isInteger(value) ? 0 : 2;
                    const label = Math.abs(value).toLocaleString('en-US', { minimumFractionDigits: digits, maximumFractionDigits: digits }) + ' L';
                    return signed ? (value > 0 ? '+' : value < 0 ? '-' : '') + label : label;
                },

                verdictClass(status) {
                    return VERDICT[status] ?? '';
                },

                async saveDip() {
                    if (!this.canSave) return;

                    this.dip.saving = true;
                    this.dip.error = '';

                    try {
                        const data = await FT.http.post(this.dip.storeUrl, { dip_reading: this.measuredValue, notes: this.dip.notes || null });
                        this.dip.result = data;
                        this.dip.history = [data.dip, ...this.dip.history].slice(0, 5);
                        this.dip.book = data.tank.level;
                        this.dip.bookLabel = data.tank.level_label;
                        this.paint(data.tank);
                        this.flash(data.tank.id);
                        FT.toast(data.message, data.status === 'match' ? 'success' : 'warning');
                    } catch (error) {
                        this.dip.error = error instanceof FT.HttpError ? error.first('dip_reading') : error.message;
                    } finally {
                        this.dip.saving = false;
                    }
                },

                newReading() {
                    Object.assign(this.dip, { result: null, measured: '', notes: '', error: '' });
                    this.loadBook();
                },
            };
        }
    </script>
</x-layouts.app>
