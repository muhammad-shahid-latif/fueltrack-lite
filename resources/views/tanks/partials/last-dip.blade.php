{{-- Last dip line on a tank card. Kept in sync with tankBoard().paintLastDip(). --}}
<i class="bi bi-rulers me-1"></i>
@if ($dip)
    @php($variance = (float) $dip->quantity)
    Last dip {{ $dip->business_date->format('d M') }}{{ $dip->created_at ? ', '.$dip->created_at->format('h:i A') : '' }}:
    <span @class(['fw-semibold', 'amount-in' => $variance >= 0.005, 'amount-out' => $variance <= -0.005, 'text-success' => abs($variance) < 0.005])>
        {{ abs($variance) < 0.005 ? 'Matched' : ($variance > 0 ? '+' : '').\App\Support\Format::liters($variance) }}
    </span>
@else
    No dip recorded yet
@endif
