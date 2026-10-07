<x-layouts.app title="Inventory movements" subtitle="Every liter in and out of every tank, with running balances">
    @php($types = ['delivery' => ['Deliveries', 'is-in'], 'sales' => ['Shift sales', 'is-out'], 'dip' => ['Dip corrections', 'is-adjust'], 'transfer_in' => ['Transfers in', 'is-in'], 'transfer_out' => ['Transfers out', 'is-out']])

    <form method="GET" class="card mb-4">
        <div class="card-body d-flex flex-wrap align-items-end gap-2 p-3">
            <div>
                <label for="from" class="form-label small mb-1">From</label>
                <input type="date" id="from" name="from" value="{{ $from->toDateString() }}" max="{{ today()->toDateString() }}" class="form-control">
            </div>
            <div>
                <label for="to" class="form-label small mb-1">To</label>
                <input type="date" id="to" name="to" value="{{ $to->toDateString() }}" max="{{ today()->toDateString() }}" class="form-control">
            </div>
            <div>
                <label for="tank" class="form-label small mb-1">Tank</label>
                <select id="tank" name="tank" class="form-select">
                    <option value="">All tanks</option>
                    @foreach ($allTanks as $tank)
                        <option value="{{ $tank->id }}" @selected(($filters['tank'] ?? null) == $tank->id)>{{ $tank->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="type" class="form-label small mb-1">Movement</label>
                <select id="type" name="type" class="form-select">
                    <option value="">Everything</option>
                    @foreach ($types as $value => [$label])
                        <option value="{{ $value }}" @selected(($filters['type'] ?? null) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-dark"><i class="bi bi-funnel me-1"></i> Show</button>
            @error('to') <div class="text-danger small w-100">{{ $message }}</div> @enderror
        </div>
    </form>

    <div class="row g-3 mb-4">
        @foreach ($ledgers as $ledger)
            <div class="col-md-6 col-xl-3">
                <a href="{{ route('tanks.show', ['tank' => $ledger['tank'], 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}" class="card is-lift h-100 text-decoration-none text-reset">
                    <div class="card-body p-3 d-flex gap-3 align-items-center">
                        <x-tank-3d :row="['tank' => $ledger['tank'], 'percent' => $ledger['tank']->capacity > 0 ? min(100, round(max(0, $ledger['closing']) / (float) $ledger['tank']->capacity * 100, 1)) : 0, 'status' => 'ok']" mini class="p-0" />
                        <div class="flex-grow-1" style="min-width: 0;">
                            <div class="fw-bold text-truncate">{{ $ledger['tank']->name }}</div>
                            <div class="small text-secondary">@liters($ledger['opening']) → <strong class="text-body">@liters($ledger['closing'])</strong></div>
                            <div class="small mt-1">
                                <span class="amount-in">+@liters($ledger['received'])</span> ·
                                <span class="amount-out">−@liters($ledger['sold'])</span>
                                @if ($ledger['adjusted'] != 0)
                                    · <span class="text-secondary">{{ $ledger['adjusted'] > 0 ? '+' : '' }}@liters($ledger['adjusted'])</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    <div class="card">
        <div class="card-header">Movements, newest first <span class="badge text-bg-light border ms-auto fw-normal">{{ $rows->count() }}</span></div>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Tank</th>
                        <th>Movement</th>
                        <th class="text-end">Liters</th>
                        <th class="text-end">Tank balance</th>
                        <th class="d-none d-lg-table-cell">By</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="text-nowrap">{{ $row['date']->format('d M Y') }}<div class="small text-secondary">{{ $row['at']->format('h:i A') }}</div></td>
                            <td>
                                <span class="d-flex align-items-center gap-2 {{ \App\Support\Grade::cssClass($row['tank']->fuelProduct) }}"><span class="grade-dot"></span>{{ $row['tank']->name }}</span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="icon-chip {{ $types[$row['type']][1] ?? '' }}"><i class="bi bi-{{ $row['icon'] }}"></i></span>
                                    <span><span class="fw-semibold d-block">{{ $row['label'] }}</span>@if ($row['detail'])<span class="small text-secondary">{{ $row['detail'] }}</span>@endif</span>
                                </div>
                            </td>
                            <td @class(['text-end ft-num fw-bold', 'amount-in' => $row['quantity'] > 0, 'amount-out' => $row['quantity'] < 0])>{{ $row['quantity'] > 0 ? '+' : '' }}@liters($row['quantity'])</td>
                            <td class="text-end ft-num">@liters($row['balance'])</td>
                            <td class="d-none d-lg-table-cell small text-secondary">{{ $row['by'] ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty-state"><i class="bi bi-arrow-left-right"></i>No movements in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
