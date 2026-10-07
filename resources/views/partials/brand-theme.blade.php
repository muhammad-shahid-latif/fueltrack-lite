{{-- The dealer's fuel network theme. Without one, the neutral FuelTrack colours from app.css apply. --}}
@php($brand = \App\Support\Brand::current())
<meta name="theme-color" content="{{ $brand?->color('surface-800') ?? '#05303b' }}">
@if ($brand)
    <style>html:root { {!! $brand->cssVariables() !!} }</style>
@endif
