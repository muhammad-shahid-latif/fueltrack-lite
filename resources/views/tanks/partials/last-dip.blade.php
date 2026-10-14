{{-- Last dip chip on a tank card. Kept in sync with tankBoard().paintLastDip(). --}}
@php
    $variance = $dip ? (float) $dip->quantity : 0.0;
    $status = ! $dip ? 'none' : (abs($variance) < 0.005 ? 'match' : ($variance > 0 ? 'gain' : 'loss'));
    $chip = [
        'none' => ['rulers', 'Not checked'],
        'match' => ['check-circle-fill', 'Matched'],
        'gain' => ['arrow-up-circle-fill', '+'.\App\Support\Format::liters($variance)],
        'loss' => ['arrow-down-circle-fill', \App\Support\Format::liters($variance)],
    ][$status];
@endphp
<div class="dip-chip is-{{ $status }}">
    <i class="bi bi-{{ $chip[0] }} dip-chip-icon"></i>
    <div class="dip-chip-text">
        <div class="dip-chip-label">Last dip</div>
        <div class="dip-chip-when">{{ $dip ? $dip->business_date->format('d M').($dip->created_at ? ' · '.$dip->created_at->format('h:i A') : '') : 'No reading yet' }}</div>
    </div>
    <span class="dip-chip-value">{{ $chip[1] }}</span>
</div>
