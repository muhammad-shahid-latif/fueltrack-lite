<x-layouts.app :title="'Closing · '.$closing->business_date->format('d M Y')">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h2 class="h4 fw-bold mb-1">{{ $closing->business_date->format('l, d F Y') }}</h2>
            <p class="text-secondary mb-0">
                <span class="badge text-bg-secondary me-1"><i class="bi bi-lock-fill me-1"></i>Closed</span>
                by {{ $closing->closer?->name ?? '—' }} at {{ $closing->created_at->format('h:i A') }}
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('closings.index') }}" class="btn btn-light">All closings</a>
            @if (auth()->user()->isOwner())
                <a href="{{ route('closings.edit', $closing) }}" class="btn btn-outline-dark">
                    <i class="bi bi-pencil me-1"></i> Edit cash count
                </a>
                <form method="POST" action="{{ route('closings.destroy', $closing) }}" x-data="{ sure: false }" class="d-flex">
                    @csrf
                    @method('DELETE')
                    <button type="button" class="btn btn-outline-danger" x-show="! sure" @click="sure = true">
                        <i class="bi bi-unlock me-1"></i> Reopen day
                    </button>
                    <span x-show="sure" x-cloak><span class="d-flex flex-wrap align-items-center gap-2 border border-danger-subtle rounded-3 px-2 py-1">
                        <span class="small">Delete this closing and unlock the day?</span>
                        <button type="submit" class="btn btn-sm btn-danger">Yes, reopen</button>
                        <button type="button" class="btn btn-sm btn-light" @click="sure = false">Keep closed</button>
                    </span></span>
                </form>
            @endif
        </div>
    </div>

    @if ($closing->hasCashDifference())
        <div class="alert alert-danger d-flex align-items-center gap-3 fs-5">
            <i class="bi bi-exclamation-triangle-fill fs-3"></i>
            <div>
                <div class="fw-bold">Cash {{ $closing->difference < 0 ? 'short' : 'excess' }}: @money(abs((float) $closing->difference))</div>
                @if ($closing->notes)
                    <div class="small">{{ $closing->notes }}</div>
                @endif
            </div>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">Sales summary</div>
                <div class="card-body">
                    <dl class="row mb-0 fs-6">
                        <dt class="col-7 fw-normal text-secondary">Fuel sold</dt>
                        <dd class="col-5 text-end">@liters($closing->total_liters)</dd>
                        <dt class="col-7 fw-semibold">Total sales</dt>
                        <dd class="col-5 text-end fw-bold">@money($closing->total_sales)</dd>
                        <dt class="col-7 fw-normal text-secondary ps-4">Cash sales</dt>
                        <dd class="col-5 text-end">@money($closing->cash_sales)</dd>
                        <dt class="col-7 fw-normal text-secondary ps-4">Card sales</dt>
                        <dd class="col-5 text-end">@money($closing->card_sales)</dd>
                        <dt class="col-7 fw-normal text-secondary ps-4">Credit sales</dt>
                        <dd class="col-5 text-end">@money($closing->credit_sales)</dd>
                        <dt class="col-7 fw-semibold mt-2">Expenses</dt>
                        <dd class="col-5 text-end mt-2">@money($closing->total_expenses)</dd>
                    </dl>
                    <div class="mt-3">
                        @include('closings.partials.owed-explainer', [
                            'credit' => (float) $closing->credit_sales,
                            'billed' => (float) $closing->owed_fuel_billed,
                            'given' => (float) $closing->owed_fuel_given,
                        ])
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">Cash reconciliation</div>
                <div class="card-body">
                    <table class="table mb-0">
                        <tr><td class="text-secondary">Opening cash</td><td class="text-end">@money($closing->opening_cash)</td></tr>
                        <tr><td class="text-secondary">+ Cash sales</td><td class="text-end">@money($closing->cash_sales)</td></tr>
                        @if ((float) $closing->customer_receipts > 0)
                            <tr><td class="text-secondary">+ Received from credit customers</td><td class="text-end">@money($closing->customer_receipts)</td></tr>
                        @endif
                        <tr><td class="text-secondary">− Cash expenses</td><td class="text-end">@money(-$closing->cash_expenses)</td></tr>
                        @if ((float) $closing->supplier_payments > 0)
                            <tr><td class="text-secondary">− Cash paid to suppliers</td><td class="text-end">@money(-$closing->supplier_payments)</td></tr>
                        @endif
                        <tr class="fw-bold"><td>Expected cash</td><td class="text-end">@money($closing->expected_cash)</td></tr>
                        <tr class="fw-bold"><td>Actual cash</td><td class="text-end">@money($closing->actual_cash)</td></tr>
                        <tr @class(['fw-bold', 'table-danger' => $closing->hasCashDifference(), 'table-success' => ! $closing->hasCashDifference()])>
                            <td>Difference</td>
                            <td class="text-end">{{ $closing->difference > 0 ? '+' : '' }}@money($closing->difference)</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
