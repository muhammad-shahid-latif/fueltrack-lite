@props(['label', 'value', 'icon', 'tone' => 'teal', 'hint' => null])

<div {{ $attributes->merge(['class' => "card stat-card tilt-glare tile-{$tone} h-100"]) }} data-tilt="5">
    <div class="card-body d-flex align-items-center gap-3 p-3 p-xl-4">
        <div class="stat-icon tilt-pop"><i class="bi bi-{{ $icon }}"></i></div>
        <div style="min-width: 0;">
            <div class="stat-label">{{ $label }}</div>
            <div class="stat-value">{{ $value }}</div>
            @if ($hint)
                <div class="text-secondary" style="font-size: .76rem;">{{ $hint }}</div>
            @endif
        </div>
    </div>
</div>
