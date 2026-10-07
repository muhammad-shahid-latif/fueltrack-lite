@php($supplier = $account['supplier'])
<x-layouts.app :title="$supplier->name" :subtitle="'Supplier · '.$supplier->termsLabel()">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <a href="{{ route('suppliers.index') }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i> All suppliers</a>
        <div class="d-flex gap-2">
            <a href="{{ route('purchases.create') }}" class="btn btn-light"><i class="bi bi-truck me-1"></i> New purchase</a>
            <a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-outline-dark"><i class="bi bi-pencil me-1"></i> Edit</a>
            <button type="button" class="btn btn-light" onclick="window.print()"><i class="bi bi-printer"></i></button>
        </div>
    </div>

    <section class="hero-3d mb-4" data-tilt="1">
        <div class="row g-3 align-items-center">
            <div class="col-lg-4 d-flex gap-3 align-items-center">
                <span class="party-avatar is-supplier" style="width: 64px; height: 64px; font-size: 1.6rem;">{{ str($supplier->name)->substr(0, 1)->upper() }}</span>
                <div>
                    <div class="hero-label">Payable now</div>
                    <div class="hero-value">@money($account['balance'])</div>
                    <div class="hero-sub">{{ $supplier->contact_person }} {{ $supplier->phone ? '· '.$supplier->phone : '' }}</div>
                </div>
            </div>
            <div class="col-6 col-lg-2"><div class="hero-tile"><div class="hero-label">Purchased</div><div class="hero-value is-white" style="font-size: 1.3rem;">@money(round($account['purchased']))</div></div></div>
            <div class="col-6 col-lg-2"><div class="hero-tile"><div class="hero-label">Paid</div><div class="hero-value is-white" style="font-size: 1.3rem;">@money(round($account['paid']))</div></div></div>
            <div class="col-6 col-lg-2"><div class="hero-tile"><div class="hero-label">Overdue</div><div @class(['hero-value', 'is-white' => $account['overdue'] <= 0]) style="font-size: 1.3rem;">@money(round($account['overdue']))</div></div></div>
            <div class="col-6 col-lg-2"><div class="hero-tile"><div class="hero-label">Next due</div><div class="hero-value is-white" style="font-size: 1.3rem;">{{ $account['next_due']?->format('d M') ?? '—' }}</div></div></div>
        </div>
    </section>

    <div class="row g-4">
        <div class="col-xl-4 d-print-none">
            <div class="card mb-4">
                <div class="card-header">Record a payment</div>
                <div class="card-body p-4">
                    @if ($account['balance'] <= 0)
                        <p class="text-secondary mb-0"><i class="bi bi-check2-circle text-success me-1"></i>Nothing is payable to {{ $supplier->name }}.</p>
                    @else
                        <form method="POST" action="{{ route('suppliers.payments.store', $supplier) }}" novalidate x-data="{ submitting: false, amount: @js(old('amount', '')) }" @submit="submitting = true">
                            @csrf
                            <label for="amount" class="form-label">Amount (Rs.)</label>
                            <div class="input-group">
                                <input type="text" inputmode="decimal" id="amount" name="amount" x-model="amount" class="form-control form-control-lg ft-num @error('amount') is-invalid @enderror">
                                <button type="button" class="btn btn-light" @click="amount = @js(\App\Support\Format::meter($account['balance']))">Full</button>
                                @error('amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <label class="form-label mt-3 d-block">Paid by</label>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach ($paymentMethods as $method)
                                    <input type="radio" class="btn-check" name="payment_method" id="sp_{{ $method->value }}" value="{{ $method->value }}" @checked(old('payment_method', 'bank') === $method->value)>
                                    <label class="btn btn-outline-dark btn-sm" for="sp_{{ $method->value }}">{{ $method->label() }}</label>
                                @endforeach
                            </div>
                            <input type="text" name="reference" value="{{ old('reference') }}" maxlength="50" class="form-control mt-3" placeholder="Cheque / transaction no. (optional)">
                            <input type="text" name="notes" value="{{ old('notes') }}" maxlength="500" class="form-control mt-2" placeholder="Notes (optional)">
                            <div class="form-text">Cash payments come out of today's cash drawer at closing.</div>
                            <button type="submit" class="btn btn-warning w-100 mt-3" :disabled="submitting"><i class="bi bi-send me-1"></i> Save payment</button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-header">Details</div>
                <div class="card-body small">
                    <dl class="row mb-0">
                        <dt class="col-5 text-secondary fw-normal">Address</dt><dd class="col-7">{{ $supplier->address ?? '—' }}</dd>
                        <dt class="col-5 text-secondary fw-normal">Credit terms</dt><dd class="col-7">{{ $supplier->termsLabel() }}</dd>
                        <dt class="col-5 text-secondary fw-normal">Opening balance</dt><dd class="col-7">@money($supplier->opening_balance)</dd>
                        <dt class="col-5 text-secondary fw-normal">Notes</dt><dd class="col-7 mb-0">{{ $supplier->notes ?? '—' }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="card mb-4">
                <div class="card-header">Bills &amp; payment status</div>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead><tr><th>Bill</th><th>Due</th><th class="text-end">Amount</th><th class="text-end">Paid</th><th class="text-end">Remaining</th><th>Status</th></tr></thead>
                        <tbody>
                            @forelse ($account['bills'] as $bill)
                                <tr>
                                    <td><span class="fw-semibold">{{ $bill['label'] }}</span><div class="small text-secondary">{{ $bill['date']->format('d M Y') }}{{ $bill['purchase']?->tank ? ' · '.$bill['purchase']->tank->name : '' }}</div></td>
                                    <td class="text-nowrap">{{ $bill['due']->format('d M Y') }}@if ($bill['days_overdue'] > 0)<div class="small amount-out">{{ $bill['days_overdue'] }} days late</div>@endif</td>
                                    <td class="text-end ft-num">@money($bill['amount'])</td>
                                    <td class="text-end ft-num amount-in">@money($bill['paid'])</td>
                                    <td class="text-end ft-num fw-bold">@money($bill['remaining'])</td>
                                    <td><span class="badge {{ $bill['status']->badge() }}">{{ $bill['status']->label() }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="empty-state"><i class="bi bi-receipt"></i>No bills yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Ledger</div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle ledger-table">
                        <thead><tr><th>Date</th><th>Entry</th><th class="text-end">Bill (+)</th><th class="text-end">Paid (−)</th><th class="text-end">Balance</th><th class="d-print-none"></th></tr></thead>
                        <tbody>
                            @forelse ($account['ledger'] as $row)
                                <tr @class(['is-opening' => $row['type'] === 'opening'])>
                                    <td class="text-nowrap">{{ $row['date']->format('d M Y') }}</td>
                                    <td><span class="fw-semibold">{{ $row['label'] }}</span>@if ($row['detail'])<div class="small text-secondary">{{ $row['detail'] }}</div>@endif</td>
                                    <td class="text-end ft-num">{{ $row['debit'] > 0 ? \App\Support\Format::money($row['debit']) : '' }}</td>
                                    <td class="text-end ft-num amount-in">{{ $row['credit'] > 0 ? \App\Support\Format::money($row['credit']) : '' }}</td>
                                    <td class="text-end ft-num fw-bold">@money($row['balance'])</td>
                                    <td class="text-end d-print-none">
                                        @if ($row['type'] === 'payment')
                                            <form method="POST" action="{{ route('supplier-payments.destroy', $row['model']) }}" x-data="{ sure: false }" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="button" class="btn btn-sm btn-link text-danger" x-show="! sure" @click="sure = true" aria-label="Delete payment"><i class="bi bi-trash3"></i></button>
                                                <span x-show="sure" x-cloak><button type="submit" class="btn btn-sm btn-danger">Delete</button></span>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="empty-state"><i class="bi bi-journal"></i>No entries yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
