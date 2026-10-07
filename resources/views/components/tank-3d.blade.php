@props(['row', 'mini' => false])

{{-- A tank drawn as a lit cylinder; the liquid height is the live level. --}}
<div {{ $attributes->merge(['class' => 'tank-stage']) }}>
    <div @class(['tank-3d', \App\Support\Grade::cssClass($row['tank']->fuelProduct), 'is-mini' => $mini, 'is-low' => $row['status'] === 'low', 'is-critical' => $row['status'] === 'critical'])
         style="--fill: {{ $row['percent'] }}%;" data-tank-fill="{{ $row['tank']->id }}"
         role="img" aria-label="{{ $row['tank']->name }} {{ $row['percent'] }}% full">
        <div class="tank-body">
            <div class="tank-liquid"></div>
        </div>
        <div class="tank-top"></div>
        <div class="tank-ticks"></div>
        @unless ($mini)
            <div class="tank-readout" data-tank-percent="{{ $row['tank']->id }}">{{ round($row['percent']) }}%</div>
        @endunless
    </div>
</div>
@unless ($mini)
    <div class="tank-shadow" aria-hidden="true"></div>
@endunless
