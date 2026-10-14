{{--
    Why the credit taken off a day's cash differs from the credit billed that day.
    $credit billed, $billed owed (not given yet), $given owed from earlier slips given today, $liters optional ['billed' => L, 'given' => L].
--}}
@php($liters = $liters ?? null)
@if ($billed > 0 || $given > 0)
    <div class="owed-closing">
        <div class="owed-closing-title"><i class="bi bi-hourglass-split"></i>Credit slips with fuel given on another day</div>
        <table class="table table-sm mb-2">
            <tr><td>Credit billed today</td><td class="text-end ft-num">@money($credit)</td></tr>
            @if ($billed > 0)
                <tr class="is-minus">
                    <td>− Billed today, fuel still to give{{ $liters ? ' ('.\App\Support\Format::liters($liters['billed']).')' : '' }}</td>
                    <td class="text-end ft-num">@money(-$billed)</td>
                </tr>
            @endif
            @if ($given > 0)
                <tr class="is-plus">
                    <td>+ Owed fuel given today, billed earlier{{ $liters ? ' ('.\App\Support\Format::liters($liters['given']).')' : '' }}</td>
                    <td class="text-end ft-num">@money($given)</td>
                </tr>
            @endif
            <tr class="fw-bold"><td>Credit fuel that left the nozzles today</td><td class="text-end ft-num">@money(round($credit - $billed + $given, 2))</td></tr>
        </table>
        <div class="small">
            @if ($billed > 0)
                <div><i class="bi bi-info-circle me-1"></i><strong>@money($billed)</strong>{{ $liters ? ' ('.\App\Support\Format::liters($liters['billed']).')' : '' }} is on customers' slips but not in today's meter readings, so it is <strong>not</strong> taken off today's cash. It counts on the day the fuel is given.</div>
            @endif
            @if ($given > 0)
                <div class="mt-1"><i class="bi bi-info-circle me-1"></i><strong>@money($given)</strong> of fuel pumped today was billed on an earlier slip, so it brings no cash today.</div>
            @endif
        </div>
    </div>
@endif
