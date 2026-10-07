<x-layouts.app :title="'Edit cash count · '.$closing->business_date->format('d M Y')" subtitle="The cash difference is recalculated when you save">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <h2 class="h4 fw-bold mb-0">{{ $closing->business_date->format('l, d F Y') }}</h2>
        <span class="chip"><i class="bi bi-lock-fill text-secondary"></i>Day stays closed</span>
    </div>

    @include('closings.partials.cash-form', [
        'action' => route('closings.update', $closing),
        'method' => 'PUT',
        'date' => $closing->business_date,
        'values' => [
            'opening_cash' => (float) $closing->opening_cash,
            'card_sales' => (float) $closing->card_sales,
            'actual_cash' => (float) $closing->actual_cash,
            'notes' => $closing->notes,
        ],
        'submitLabel' => 'Save cash count',
        'submitIcon' => 'check2-circle',
        'cancelUrl' => route('closings.show', $closing),
        'footer' => 'Only the cash count changes. To correct readings, sales or expenses of this day, reopen it instead.',
    ])
</x-layouts.app>
