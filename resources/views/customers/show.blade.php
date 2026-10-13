@php($customer = $account['customer'])
@php($used = $customer->credit_limit ? min(100, max(0, round($account['balance'] / (float) $customer->credit_limit * 100))) : null)
<x-layouts.app :title="$customer->name" :subtitle="'Customer ledger'.($customer->vehicle_number ? ' · '.$customer->vehicle_number : '')">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4 d-print-none">
        <a href="{{ route('customers.index') }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i> All customers</a>
        <div class="d-flex flex-wrap gap-2">
            <form method="GET" class="d-flex gap-2 align-items-center">
                <input type="date" name="from" value="{{ $from?->toDateString() }}" class="form-control form-control-sm" aria-label="Statement from">
                <input type="date" name="to" value="{{ $to?->toDateString() }}" class="form-control form-control-sm" aria-label="Statement to">
                <button class="btn btn-sm btn-dark" aria-label="Filter ledger"><i class="bi bi-funnel"></i></button>
            </form>
            <button type="button" class="btn btn-light" onclick="window.print()"><i class="bi bi-printer me-1"></i> Statement</button>
            @if (auth()->user()->isOwner())
                <a href="{{ route('customers.edit', $customer) }}" class="btn btn-outline-dark"><i class="bi bi-pencil me-1"></i> Edit</a>
            @endif
        </div>
    </div>

    <section class="hero-3d mb-4" data-tilt="1">
        <div class="row g-3 align-items-center">
            <div class="col-lg-3 d-flex gap-3 align-items-center">
                <span class="party-avatar is-customer" style="width: 64px; height: 64px; font-size: 1.4rem;">{{ $customer->initials() }}</span>
                <div>
                    <div class="hero-label">Outstanding balance</div>
                    <div class="hero-value">@money($account['balance'])</div>
                    <div class="hero-sub">{{ $customer->phone ?? '' }} {{ $customer->address ? '· '.$customer->address : '' }}</div>
                </div>
            </div>
            <div class="col-4 col-lg-2"><div class="hero-tile"><div class="hero-label">Opening balance</div><div class="hero-value is-white" style="font-size: 1.3rem;">@money(round($customer->opening_balance))</div></div></div>
            <div class="col-4 col-lg-2"><div class="hero-tile"><div class="hero-label">+ Credit given</div><div class="hero-value is-white" style="font-size: 1.3rem;">@money(round($account['credited']))</div></div></div>
            <div class="col-4 col-lg-2"><div class="hero-tile"><div class="hero-label">− Received</div><div class="hero-value is-white" style="font-size: 1.3rem;">@money(round($account['received']))</div></div></div>
            <div class="col-12 col-lg-3">
                <div class="hero-tile">
                    <div class="d-flex justify-content-between hero-label mb-2">
                        <span>Credit limit</span>
                        <span>{{ $customer->credit_limit !== null ? \App\Support\Format::money(round($customer->credit_limit)) : 'No limit' }}</span>
                    </div>
                    @if ($used !== null)
                        <div class="limit-meter mb-2"><span style="width: {{ $used }}%; background-position: {{ $used }}% 0;"></span></div>
                        <div class="hero-sub">{{ $used }}% used · room for {{ \App\Support\Format::money(max(0, round($account['available']))) }}</div>
                    @else
                        <div class="hero-sub">Government / trusted account</div>
                    @endif
                </div>
            </div>
        </div>
        <div class="hero-sub mt-3 ft-num">
            <i class="bi bi-calculator me-1"></i>Opening @money($customer->opening_balance) + Credit @money($account['credited']) − Received @money($account['received']) = <strong class="text-white">Outstanding @money($account['balance'])</strong>
        </div>
    </section>

    <div class="row g-4">
        <div class="col-xl-4 d-print-none">
            <div class="card mb-4" x-data="{ tab: '{{ $errors->has('amount') && old('payment_method') ? 'pay' : 'credit' }}' }">
                <div class="card-header d-flex">
                    <div class="seg">
                        <a href="#" :class="tab === 'credit' && 'active'" @click.prevent="tab = 'credit'"><i class="bi bi-fuel-pump me-1"></i>Credit sale</a>
                        <a href="#" :class="tab === 'pay' && 'active'" @click.prevent="tab = 'pay'"><i class="bi bi-cash-coin me-1"></i>Receive</a>
                    </div>
                </div>
                <div class="card-body p-4">
                    <form x-show="tab === 'credit'" method="POST" action="{{ route('customers.credits.store', $customer) }}" novalidate
                          x-data="{ fuel: @js(old('fuel_product_id', (string) ($fuels->first()?->id ?? ''))), qty: @js(old('quantity', '')), rates: @js($fuels->mapWithKeys(fn ($f) => [$f->id => (float) $f->selling_price])), submitting: false,
                                    get total() { const t = parseFloat(this.qty) * (this.rates[this.fuel] ?? 0); return Number.isFinite(t) ? Math.round(t * 100) / 100 : 0 } }"
                          @submit="submitting = true">
                        @csrf
                        <label class="form-label">Fuel</label>
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            @foreach ($fuels as $fuel)
                                <input type="radio" class="btn-check" name="fuel_product_id" id="cf_{{ $fuel->id }}" value="{{ $fuel->id }}" x-model="fuel">
                                <label class="btn btn-outline-dark btn-sm" for="cf_{{ $fuel->id }}">{{ $fuel->name }} · {{ \App\Support\Format::number($fuel->selling_price) }}</label>
                            @endforeach
                        </div>
                        <label for="quantity" class="form-label">Liters</label>
                        <input type="text" inputmode="decimal" id="quantity" name="quantity" x-model="qty" class="form-control form-control-lg ft-num @error('quantity') is-invalid @enderror" placeholder="50">
                        @error('quantity') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        @error('amount') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        <div class="row g-2 mt-1">
                            <div class="col-6"><input type="text" name="vehicle_number" value="{{ old('vehicle_number', $customer->vehicle_number) }}" maxlength="30" class="form-control" placeholder="Vehicle no."></div>
                            <div class="col-6"><input type="text" name="slip_number" value="{{ old('slip_number') }}" maxlength="30" class="form-control" placeholder="Slip no."></div>
                        </div>
                        <div class="calc-panel d-flex justify-content-between align-items-center mt-3">
                            <span class="calc-label fs-6">Credit amount</span>
                            <span class="calc-value is-total" x-text="total > 0 ? 'Rs. ' + total.toLocaleString('en-US') : '—'"></span>
                        </div>
                        <button type="submit" class="btn btn-warning w-100 mt-3" :disabled="submitting || total <= 0"><i class="bi bi-journal-plus me-1"></i> Add to account</button>
                    </form>

                    <form x-show="tab === 'pay'" x-cloak method="POST" action="{{ route('customers.payments.store', $customer) }}" novalidate x-data="{ submitting: false, amount: @js(old('payment_method') ? old('amount', '') : '') }" @submit="submitting = true">
                        @csrf
                        <label for="pay_amount" class="form-label">Amount received (Rs.)</label>
                        <div class="input-group">
                            <input type="text" inputmode="decimal" id="pay_amount" name="amount" x-model="amount" class="form-control form-control-lg ft-num">
                            <button type="button" class="btn btn-light" @click="amount = @js(\App\Support\Format::meter(max(0, $account['balance'])))">Full</button>
                        </div>
                        <label class="form-label mt-3 d-block">Received by</label>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach ($paymentMethods as $method)
                                <input type="radio" class="btn-check" name="payment_method" id="cp_{{ $method->value }}" value="{{ $method->value }}" @checked(old('payment_method', 'cash') === $method->value)>
                                <label class="btn btn-outline-dark btn-sm" for="cp_{{ $method->value }}">{{ $method->label() }}</label>
                            @endforeach
                        </div>
                        <input type="text" name="reference" value="{{ old('reference') }}" maxlength="50" class="form-control mt-3" placeholder="Cheque / receipt no. (optional)">
                        <div class="form-text">Cash received goes into today's cash drawer at closing.</div>
                        <button type="submit" class="btn btn-success w-100 mt-3" :disabled="submitting"><i class="bi bi-check2-circle me-1"></i> Save payment</button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Aging of the balance</div>
                <div class="card-body">
                    @php($agingTotal = max(0.01, array_sum($account['aging'])))
                    <div class="aging-bar mb-3">
                        @foreach (array_values($account['aging']) as $i => $amount)
                            <span class="aging-{{ $i }}" style="width: {{ round($amount / $agingTotal * 100, 1) }}%"></span>
                        @endforeach
                    </div>
                    @foreach (array_keys($account['aging']) as $i => $bucket)
                        <div class="d-flex justify-content-between small py-1">
                            <span><span class="d-inline-block rounded-circle aging-{{ $i }} me-2" style="width: 9px; height: 9px;"></span>{{ $bucket }}</span>
                            <span class="ft-num fw-semibold">@money($account['aging'][$bucket])</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="card mb-4">
                <div class="card-header">Unpaid credit <span class="badge text-bg-light border fw-normal ms-auto">{{ $account['debts']->count() }}</span></div>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead><tr><th>Date</th><th>Credit</th><th class="text-end">Amount</th><th class="text-end">Paid</th><th class="text-end">Remaining</th><th>Status</th></tr></thead>
                        <tbody>
                            @forelse ($account['debts'] as $debt)
                                <tr>
                                    <td class="text-nowrap">{{ $debt['date']->format('d M Y') }}<div class="small text-secondary">{{ (int) $debt['date']->diffInDays(today()) }} days ago</div></td>
                                    <td>{{ $debt['label'] }}</td>
                                    <td class="text-end ft-num">@money($debt['amount'])</td>
                                    <td class="text-end ft-num amount-in">@money($debt['paid'])</td>
                                    <td class="text-end ft-num fw-bold">@money($debt['remaining'])</td>
                                    <td><span class="badge {{ $debt['status']->badge() }}">{{ $debt['status']->label() }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="empty-state"><i class="bi bi-check2-circle"></i>Nothing outstanding.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card report-sheet">
                <div class="card-header">Ledger{{ $from || $to ? ' · '.($from?->format('d M Y') ?? 'start').' to '.($to?->format('d M Y') ?? 'today') : '' }}</div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle ledger-table">
                        <thead><tr><th>Date</th><th>Entry</th><th class="text-end">Credit (+)</th><th class="text-end">Paid (−)</th><th class="text-end">Balance</th><th class="d-print-none"></th></tr></thead>
                        <tbody>
                            @forelse ($account['ledger'] as $row)
                                <tr @class(['is-opening' => $row['type'] === 'opening'])>
                                    <td class="text-nowrap">{{ $row['date']->format('d M Y') }}</td>
                                    <td><span class="fw-semibold">{{ $row['label'] }}</span>@if ($row['detail'])<div class="small text-secondary">{{ $row['detail'] }}</div>@endif</td>
                                    <td class="text-end ft-num">{{ $row['debit'] > 0 ? \App\Support\Format::money($row['debit']) : '' }}</td>
                                    <td class="text-end ft-num amount-in">{{ $row['credit'] > 0 ? \App\Support\Format::money($row['credit']) : '' }}</td>
                                    <td class="text-end ft-num fw-bold">@money($row['balance'])</td>
                                    <td class="text-end d-print-none">
                                        @if (auth()->user()->isOwner() && in_array($row['type'], ['credit', 'payment'], true) && ! ($row['type'] === 'credit' && $row['model']->isCounterSale()))
                                            <form method="POST" action="{{ $row['type'] === 'credit' ? route('credits.destroy', $row['model']) : route('customer-payments.destroy', $row['model']) }}" x-data="{ sure: false }" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="button" class="btn btn-sm btn-link text-danger" x-show="! sure" @click="sure = true" aria-label="Delete entry"><i class="bi bi-trash3"></i></button>
                                                <span x-show="sure" x-cloak><button type="submit" class="btn btn-sm btn-danger">Delete</button></span>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="empty-state"><i class="bi bi-journal"></i>No entries.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
