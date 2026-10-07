@php
    $width = 700;
    $height = 300;
    $padTop = 16;
    $padBottom = 32;
    $padLeft = 56;
    $plotHeight = $height - $padTop - $padBottom;
    $plotWidth = $width - $padLeft;

    $max = max($days->max('sales'), 1);
    // Round the axis up to a clean step so gridlines land on readable values.
    $step = 10 ** floor(log10($max));
    $axisMax = ceil($max / $step) * $step;
    $gridLines = 4;

    $slot = $plotWidth / max($days->count(), 1);
    $barWidth = min(56, $slot * 0.6);

    $points = $days->values()->map(fn (array $day, int $index) => [
        'label' => $day['date']->format('D, d M'),
        'short' => $day['date']->isToday() ? 'Today' : $day['date']->format('D'),
        'sales' => \App\Support\Format::money($day['sales']),
        'liters' => \App\Support\Format::liters($day['liters']),
        'x' => $padLeft + $index * $slot + ($slot - $barWidth) / 2,
        'h' => $day['sales'] / $axisMax * $plotHeight,
        'isToday' => $day['date']->isToday(),
    ]);

    $axisLabel = fn (float $value) => $value >= 1_000_000
        ? rtrim(rtrim(number_format($value / 1_000_000, 1), '0'), '.').'M'
        : ($value >= 1000 ? number_format($value / 1000).'k' : (string) $value);
@endphp

<div class="card h-100" x-data="{ active: null, points: @js($points) }">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Sales, last 7 days</span>
        <span class="small fw-normal text-secondary" x-show="active !== null" x-cloak
              x-text="active !== null ? `${points[active].label} · ${points[active].sales} · ${points[active].liters}` : ''"></span>
    </div>
    <div class="card-body">
        <svg viewBox="0 0 {{ $width }} {{ $height }}" class="w-100 h-auto" role="img"
             aria-label="Bar chart of daily fuel sales for the last seven days">
            @for ($i = 0; $i <= $gridLines; $i++)
                @php($y = $padTop + $plotHeight - $plotHeight * $i / $gridLines)
                <line x1="{{ $padLeft }}" x2="{{ $width }}" y1="{{ $y }}" y2="{{ $y }}"
                      stroke="currentColor" stroke-opacity="{{ $i === 0 ? '0.35' : '0.1' }}" stroke-width="1" />
                <text x="{{ $padLeft - 8 }}" y="{{ $y + 4 }}" text-anchor="end" font-size="11" fill="currentColor" fill-opacity="0.55">
                    {{ $axisLabel($axisMax * $i / $gridLines) }}
                </text>
            @endfor

            @foreach ($points as $index => $point)
                <g @mouseenter="active = {{ $index }}" @mouseleave="active = null" style="cursor: default;">
                    <title>{{ $point['label'] }}: {{ $point['sales'] }} ({{ $point['liters'] }})</title>
                    {{-- Invisible hit area taller than the bar. --}}
                    <rect x="{{ $point['x'] - ($slot - $barWidth) / 2 }}" y="{{ $padTop }}" width="{{ $slot }}" height="{{ $plotHeight }}" fill="transparent" />
                    @if ($point['h'] > 0)
                        @php($radius = min(4, $point['h']))
                        <path d="M{{ $point['x'] }},{{ $padTop + $plotHeight }}
                                 V{{ $padTop + $plotHeight - $point['h'] + $radius }}
                                 Q{{ $point['x'] }},{{ $padTop + $plotHeight - $point['h'] }} {{ $point['x'] + $radius }},{{ $padTop + $plotHeight - $point['h'] }}
                                 H{{ $point['x'] + $barWidth - $radius }}
                                 Q{{ $point['x'] + $barWidth }},{{ $padTop + $plotHeight - $point['h'] }} {{ $point['x'] + $barWidth }},{{ $padTop + $plotHeight - $point['h'] + $radius }}
                                 V{{ $padTop + $plotHeight }} Z"
                              style="fill: var({{ $point['isToday'] ? '--ft-amber' : '--ft-canopy-500' }})"
                              :fill-opacity="active === null || active === {{ $index }} ? 1 : 0.45" />
                    @endif
                    <text x="{{ $point['x'] + $barWidth / 2 }}" y="{{ $height - 10 }}" text-anchor="middle" font-size="12"
                          fill="currentColor" fill-opacity="{{ $point['isToday'] ? '0.9' : '0.6' }}" @if ($point['isToday']) font-weight="600" @endif>
                        {{ $point['short'] }}
                    </text>
                </g>
            @endforeach
        </svg>
        <div class="small text-muted mt-1">Today is highlighted and shows sales so far. Hover a bar for details.</div>
    </div>
</div>
