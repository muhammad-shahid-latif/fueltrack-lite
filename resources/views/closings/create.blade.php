<x-layouts.app title="Daily closing" subtitle="Count the cash drawer and lock the day">
    <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
        <form method="GET" action="{{ route('closings.create') }}">
            <label for="date" class="form-label small mb-1">Day to close</label>
            <input type="date" id="date" name="date" value="{{ $date->toDateString() }}" max="{{ today()->toDateString() }}"
                   class="form-control" onchange="this.form.submit()">
        </form>
        <span class="chip"><i class="bi bi-unlock-fill text-success"></i>Day is open</span>
    </div>

    @if ($summary['total_sales'] <= 0)
        <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i> No sales recorded for this day yet.</div>
    @endif

    @include('closings.partials.cash-form', [
        'action' => route('closings.store'),
        'method' => 'POST',
        'values' => ['opening_cash' => $defaultOpeningCash, 'card_sales' => '0', 'actual_cash' => '', 'notes' => ''],
        'submitLabel' => 'Close day',
        'submitIcon' => 'lock-fill',
        'footer' => 'Closing locks '.$date->format('d M Y').'. No more readings, purchases or expenses can be added for it.',
    ])
</x-layouts.app>
