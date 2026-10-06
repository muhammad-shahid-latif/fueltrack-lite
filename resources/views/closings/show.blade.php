<x-layouts.app :title="'Closing · '.$closing->business_date->format('d M Y')">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h2 class="h4 fw-bold mb-1">{{ $closing->business_date->format('l, d F Y') }}</h2>
            <p class="text-secondary mb-0">
                <span class="badge text-bg-secondary me-1"><i class="bi bi-lock-fill me-1"></i>Closed</span>
                by {{ $closing->closer?->name ?? '—' }} at {{ $closing->created_at->format('h:i A') }}
            </p>
        </div>
        <a href="{{ route('closings.index') }}" class="btn btn-light">All closings</a>
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
                        <tr><td class="text-secondary">− Cash expenses</td><td class="text-end">@money(-$closing->cash_expenses)</td></tr>
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
