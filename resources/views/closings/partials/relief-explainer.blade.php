{{--
    The day's PM Fuel Relief inside the closing: how much of the meter sales
    the government pays through relief tokens.
    $relief: ReliefService::totalsOn() for the day.
--}}
@if ($relief['amount'] > 0)
    <div class="relief-closing">
        <div class="relief-closing-title"><i class="bi bi-patch-check-fill"></i>PM Fuel Relief · {{ $relief['tokens'] }} {{ str('token')->plural($relief['tokens']) }}</div>
        <table class="table table-sm mb-2">
            <tr><td>Fuel given on relief (at the pump price)</td><td class="text-end ft-num">@liters($relief['liters'])</td></tr>
            <tr class="fw-bold is-govt"><td>Relief amount, paid by the government</td><td class="text-end ft-num">@money($relief['amount'])</td></tr>
        </table>
        <div class="small"><i class="bi bi-info-circle me-1"></i>This <strong>@money($relief['amount'])</strong> is part of the meter sales but is not in the drawer. It is taken off the expected cash and claimed on the <a href="{{ route('relief.index', ['date' => $date ?? null]) }}">Fuel relief</a> page.</div>
    </div>
@endif
