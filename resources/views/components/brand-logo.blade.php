@props(['brand' => \App\Support\Brand::current(), 'size' => 'md'])

{{-- The network's logo on a white plate, or a lettered badge in its colours until a logo file is added. --}}
<span {{ $attributes->class(['brand-plate', "is-{$size}"]) }}>
    @if ($brand && ($logo = $brand->logoUrl()))
        <img src="{{ $logo }}" alt="{{ $brand->fullName }}">
    @elseif ($brand)
        <span class="brand-monogram" style="--m-bg: {{ $brand->color('surface-700') }}; --m-accent: {{ $brand->color('accent') }};">{{ $brand->monogram }}</span>
    @else
        <span class="brand-monogram is-default"><i class="bi bi-fuel-pump-fill"></i></span>
    @endif
</span>
