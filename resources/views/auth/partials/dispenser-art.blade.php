@php
    $boardPrices = \App\Models\FuelProduct::query()->fuel()->active()->orderBy('id')->limit(3)->get(['name', 'selling_price']);
@endphp
<svg viewBox="0 0 520 380" width="100%" style="max-width: 520px;" role="img" aria-label="Fuel dispenser showing current prices">
    <defs>
        <linearGradient id="dsFront" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stop-color="#ffffff"/>
            <stop offset="1" stop-color="#d8e6ea"/>
        </linearGradient>
        <linearGradient id="dsSide" x1="0" y1="0" x2="1" y2="0">
            <stop offset="0" stop-color="#9fb8c0"/>
            <stop offset="1" stop-color="#6f8e98"/>
        </linearGradient>
        <linearGradient id="dsTop" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" stop-color="#ffd36b"/>
            <stop offset="1" stop-color="#e58e00"/>
        </linearGradient>
        <linearGradient id="dsScreen" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stop-color="#062a33"/>
            <stop offset="1" stop-color="#031b21"/>
        </linearGradient>
        <linearGradient id="dsHose" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" stop-color="#1b2b30"/>
            <stop offset="1" stop-color="#3a4f56"/>
        </linearGradient>
        <filter id="dsGlow" x="-20%" y="-20%" width="140%" height="140%">
            <feGaussianBlur stdDeviation="2.2" result="b"/>
            <feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge>
        </filter>
        <radialGradient id="dsFloor" cx="0.5" cy="0.5" r="0.5">
            <stop offset="0" stop-color="#000" stop-opacity="0.45"/>
            <stop offset="1" stop-color="#000" stop-opacity="0"/>
        </radialGradient>
    </defs>

    {{-- floor shadow --}}
    <ellipse cx="230" cy="352" rx="190" ry="20" fill="url(#dsFloor)"/>

    {{-- plinth --}}
    <path d="M70 330 L330 330 L370 312 L110 312 Z" fill="#7d969e"/>
    <rect x="70" y="330" width="260" height="18" rx="3" fill="#5b737b"/>

    {{-- side face (depth) --}}
    <path d="M300 70 L350 48 L350 300 L300 322 Z" fill="url(#dsSide)"/>

    {{-- front body --}}
    <rect x="100" y="70" width="200" height="252" rx="14" fill="url(#dsFront)"/>
    <rect x="100" y="70" width="200" height="252" rx="14" fill="none" stroke="#ffffff" stroke-opacity="0.8"/>

    {{-- canopy cap --}}
    <path d="M92 52 L308 52 L358 30 L142 30 Z" fill="#ffc84d"/>
    <rect x="92" y="52" width="216" height="26" rx="8" fill="url(#dsTop)"/>
    <text x="200" y="70" text-anchor="middle" font-family="Chakra Petch, sans-serif" font-weight="700" font-size="14" fill="#05303b">FUELTRACK</text>

    {{-- price screen --}}
    <rect x="118" y="94" width="164" height="{{ 22 + 30 * max($boardPrices->count(), 1) }}" rx="10" fill="url(#dsScreen)"/>
    @foreach ($boardPrices as $i => $price)
        <text x="132" y="{{ 120 + $i * 30 }}" font-family="Figtree, sans-serif" font-size="12" fill="#9fc4cc">{{ $price->name }}</text>
        <text x="268" y="{{ 121 + $i * 30 }}" text-anchor="end" font-family="Chakra Petch, sans-serif" font-weight="700" font-size="20" fill="#ffb21e" filter="url(#dsGlow)">{{ rtrim(rtrim(number_format((float) $price->selling_price, 2), '0'), '.') }}</text>
    @endforeach

    {{-- grade buttons --}}
    @foreach (['#12a66a', '#2f6fe4', '#e0294f'] as $i => $color)
        <rect x="{{ 128 + $i * 50 }}" y="226" width="40" height="16" rx="8" fill="{{ $color }}"/>
        <rect x="{{ 128 + $i * 50 }}" y="226" width="40" height="7" rx="3.5" fill="#fff" fill-opacity="0.35"/>
    @endforeach

    {{-- nozzle holster + nozzle --}}
    <rect x="300" y="160" width="40" height="70" rx="8" fill="#4b636b"/>
    <path d="M312 170 h22 a6 6 0 0 1 6 6 v20 h-28 z" fill="#1d3238"/>
    <path d="M318 196 l-6 34 h12 l4 -34 z" fill="#24393f"/>

    {{-- hose --}}
    <path d="M300 280 C 420 300, 440 210, 352 190" fill="none" stroke="url(#dsHose)" stroke-width="10" stroke-linecap="round"/>
    <path d="M300 280 C 420 300, 440 210, 352 190" fill="none" stroke="#ffffff" stroke-opacity="0.12" stroke-width="3" stroke-linecap="round"/>

    {{-- base vent lines --}}
    @foreach (range(0, 3) as $i)
        <rect x="126" y="{{ 262 + $i * 12 }}" width="148" height="4" rx="2" fill="#b6cbd1"/>
    @endforeach

</svg>
