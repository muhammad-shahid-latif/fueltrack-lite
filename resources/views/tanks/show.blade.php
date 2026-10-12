<x-layouts.app :title="$tank->name" :subtitle="$tank->fuelProduct->name.' tank · capacity '.\App\Support\Format::liters($tank->capacity)">
    @php
        $statusBadge = ['ok' => ['text-bg-success', 'Healthy'], 'low' => ['text-bg-warning', 'Low'], 'critical' => ['text-bg-danger', 'Critical']];
        $types = ['delivery' => 'is-in', 'sales' => 'is-out', 'dip' => 'is-adjust', 'transfer_in' => 'is-in', 'transfer_out' => 'is-out'];
    @endphp

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <a href="{{ route('tanks.index') }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i> All tanks</a>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('purchases.create', ['tank' => $tank->id]) }}" class="btn btn-warning"><i class="bi bi-truck me-1"></i> Receive delivery</a>
            @if (auth()->user()->isOwner())
                <a href="{{ route('tanks.edit', $tank) }}" class="btn btn-outline-dark"><i class="bi bi-pencil me-1"></i> Edit tank</a>
            @endif
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-4 col-xxl-3">
            <div class="card tank-card h-100">
                <div class="card-body p-4 text-center">
                    @if ($level)
                        <span class="badge {{ $statusBadge[$level['status']][0] }} mb-2">{{ $statusBadge[$level['status']][1] }}</span>
                        <x-tank-3d :row="$level" />
                        <dl class="row tank-meta mb-0 mt-2">
                            <div class="col-12"><dt>Full capacity</dt><dd class="fs-5">@liters($level['capacity'])</dd></div>
                            <div class="col-6"><dt>In tank</dt><dd>@liters($level['level'])</dd></div>
                            <div class="col-6"><dt>Room left</dt><dd>@liters($level['ullage'])</dd></div>
                            <div class="col-6"><dt>Avg daily sale</dt><dd>@liters($level['avg_daily'])</dd></div>
                            <div class="col-6"><dt>Days of cover</dt><dd>{{ $level['days_cover'] !== null ? number_format($level['days_cover'], 1) : '—' }}</dd></div>
                        </dl>
                    @endif
                    <div class="small text-secondary mt-2">
                        Feeds:
                        @forelse ($tank->nozzles as $nozzle)
                            <span class="badge text-bg-light border fw-normal">{{ $nozzle->label() }}</span>
                        @empty
                            no nozzles linked yet
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8 col-xxl-9">
            <div class="row g-4 h-100">
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">Record a dip reading</div>
                        <div class="card-body p-4">
                            <p class="small text-secondary">Measure the tank with the dip rod and enter the liters from the calibration chart. The book stock is corrected to the dip, and the gain or loss is kept in the ledger.</p>
                            <form method="POST" action="{{ route('tanks.dips.store', $tank) }}" novalidate x-data="{ dip: @js(old('dip_reading', '')), book: {{ $level['level'] ?? 0 }}, submitting: false,
                                  get variance() { const v = parseFloat(this.dip) - this.book; return Number.isFinite(v) ? Math.round(v * 100) / 100 : null; } }" @submit="submitting = true">
                                @csrf
                                <label for="dip_reading" class="form-label">Measured stock (L)</label>
                                <input type="text" inputmode="decimal" id="dip_reading" name="dip_reading" x-model="dip"
                                       class="form-control form-control-lg ft-num @error('dip_reading') is-invalid @enderror" placeholder="{{ \App\Support\Format::meter($level['level'] ?? 0) }}">
                                @error('dip_reading') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <div class="small mt-2" x-show="variance !== null" x-cloak>
                                    Book stock @liters($level['level'] ?? 0) ·
                                    <span class="fw-bold" :class="variance > 0 ? 'amount-in' : (variance < 0 ? 'amount-out' : '')"
                                          x-text="(variance > 0 ? 'Gain +' : (variance < 0 ? 'Loss ' : 'Exact ')) + variance.toLocaleString('en-US') + ' L'"></span>
                                </div>
                                <input type="text" name="notes" value="{{ old('notes') }}" maxlength="500" class="form-control mt-3" placeholder="Notes (optional)">
                                <button type="submit" class="btn btn-dark w-100 mt-3" :disabled="submitting || dip === ''"><i class="bi bi-rulers me-1"></i> Save dip</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">Transfer to another tank</div>
                        <div class="card-body p-4">
                            @if (! auth()->user()->isOwner())
                                <p class="text-secondary small mb-0">Only the owner can move fuel between tanks.</p>
                            @elseif ($otherTanks->isEmpty())
                                <p class="text-secondary small mb-0">There is no other active {{ $tank->fuelProduct->name }} tank to transfer into.</p>
                            @else
                                <form method="POST" action="{{ route('tanks.transfer', $tank) }}" novalidate x-data="{ submitting: false }" @submit="submitting = true">
                                    @csrf
                                    <label for="to_tank_id" class="form-label">Move into</label>
                                    <select id="to_tank_id" name="to_tank_id" class="form-select @error('to_tank_id') is-invalid @enderror">
                                        @foreach ($otherTanks as $other)
                                            <option value="{{ $other->id }}" @selected(old('to_tank_id') == $other->id)>{{ $other->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('to_tank_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    <label for="quantity" class="form-label mt-3">Liters</label>
                                    <input type="text" inputmode="decimal" id="quantity" name="quantity" value="{{ old('quantity') }}"
                                           class="form-control ft-num @error('quantity') is-invalid @enderror" placeholder="1000">
                                    @error('quantity') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    <input type="text" name="notes" value="{{ old('notes') }}" maxlength="500" class="form-control mt-3" placeholder="Reason (optional)">
                                    <button type="submit" class="btn btn-outline-dark w-100 mt-3" :disabled="submitting"><i class="bi bi-arrow-left-right me-1"></i> Transfer fuel</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex flex-wrap gap-2">
            <span><i class="bi bi-journal-text me-2"></i>Tank ledger</span>
            <form method="GET" class="d-flex flex-wrap align-items-center gap-2">
                <input type="date" name="from" value="{{ $from->toDateString() }}" max="{{ today()->toDateString() }}" class="form-control form-control-sm" aria-label="From">
                <input type="date" name="to" value="{{ $to->toDateString() }}" max="{{ today()->toDateString() }}" class="form-control form-control-sm" aria-label="To">
                <button class="btn btn-sm btn-dark" aria-label="Filter"><i class="bi bi-funnel"></i></button>
            </form>
        </div>
        <div class="card-body border-bottom py-3">
            <div class="row g-3 text-center">
                <div class="col-6 col-md-3"><div class="small text-secondary">Opening</div><div class="fw-bold ft-num">@liters($ledger['opening'])</div></div>
                <div class="col-6 col-md-3"><div class="small text-secondary">Received</div><div class="fw-bold ft-num amount-in">+ @liters($ledger['received'])</div></div>
                <div class="col-6 col-md-3"><div class="small text-secondary">Sold</div><div class="fw-bold ft-num amount-out">− @liters($ledger['sold'])</div></div>
                <div class="col-6 col-md-3"><div class="small text-secondary">Adjusted · Closing</div><div class="fw-bold ft-num">{{ $ledger['adjusted'] >= 0 ? '+' : '' }}@liters($ledger['adjusted']) · @liters($ledger['closing'])</div></div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle ledger-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Movement</th>
                        <th class="text-end">In / out</th>
                        <th class="text-end">Balance</th>
                        <th class="d-none d-md-table-cell">By</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="is-opening">
                        <td>{{ $from->format('d M Y') }}</td>
                        <td colspan="2">Opening balance</td>
                        <td class="text-end ft-num fw-bold">@liters($ledger['opening'])</td>
                        <td class="d-none d-md-table-cell"></td>
                    </tr>
                    @forelse ($ledger['rows'] as $row)
                        <tr>
                            <td class="text-nowrap">{{ $row['date']->format('d M Y') }}<div class="small text-secondary">{{ $row['at']->format('h:i A') }}</div></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="icon-chip {{ $types[$row['type']] ?? '' }}"><i class="bi bi-{{ $row['icon'] }}"></i></span>
                                    <span>
                                        <span class="fw-semibold d-block">{{ $row['label'] }}</span>
                                        @if ($row['detail']) <span class="small text-secondary">{{ $row['detail'] }}</span> @endif
                                    </span>
                                </div>
                            </td>
                            <td @class(['text-end ft-num fw-bold', 'amount-in' => $row['quantity'] > 0, 'amount-out' => $row['quantity'] < 0])>
                                {{ $row['quantity'] > 0 ? '+' : '' }}@liters($row['quantity'])
                            </td>
                            <td class="text-end ft-num">@liters($row['balance'])</td>
                            <td class="d-none d-md-table-cell small text-secondary">{{ $row['by'] ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty-state"><i class="bi bi-database"></i>No movements in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
