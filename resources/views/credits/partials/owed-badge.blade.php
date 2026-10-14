{{-- Owed-fuel status of one credit slip: still owed, or given later in full. --}}
@if ((float) $credit->owed_quantity > 0)
    @php($left = $credit->owedRemaining())
    @if ($left > 0)
        <span class="owed-badge is-open"><i class="bi bi-hourglass-split"></i>@liters($left) still to give</span>
    @else
        <span class="owed-badge is-done"><i class="bi bi-check2-all"></i>@liters($credit->owed_quantity) given later</span>
    @endif
@endif
