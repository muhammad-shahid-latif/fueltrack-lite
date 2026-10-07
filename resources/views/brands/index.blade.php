<x-layouts.guest title="Choose your fuel network" shell="network-picker">
    <div class="container-xxl py-4 py-lg-5 px-3 px-lg-4">
        <header class="d-flex align-items-center justify-content-between gap-3 mb-5">
            <span class="d-flex align-items-center gap-2">
                <span class="network-picker-mark"><i class="bi bi-fuel-pump-fill"></i></span>
                <span class="app-brand-name">FuelTrack</span>
            </span>
            <span class="small text-white-50 text-truncate">{{ \App\Support\Station::name() }}</span>
        </header>

        <div class="network-picker-intro mb-4 mb-lg-5">
            <h1>Choose your fuel network</h1>
            <p>The whole app takes on your network's logo and colours, from the login page to every report. You can switch later from the user menu.</p>
        </div>

        <div class="network-grid">
            @foreach ($brands as $brand)
                @php($isCurrent = $current?->key === $brand->key)
                <form method="POST" action="{{ route('brands.store') }}" data-tilt="7"
                      class="network-card tilt-glare {{ $isCurrent ? 'is-current' : '' }}"
                      style="--c-900: {{ $brand->color('surface-900') }}; --c-800: {{ $brand->color('surface-800') }}; --c-700: {{ $brand->color('surface-700') }}; --c-accent: {{ $brand->color('accent') }}; --c-on: {{ $brand->color('on-accent') }}; --c-led: {{ $brand->color('led') }}; --c-ground: {{ $brand->color('ground') }}; --c-stripe: {{ $brand->stripeGradient() }};">
                    @csrf
                    <input type="hidden" name="brand" value="{{ $brand->key }}">

                    <div class="network-card-stripe"></div>

                    <div class="network-card-body">
                        <div class="network-logo-stage">
                            <x-brand-logo :brand="$brand" size="md" />
                        </div>

                        <div class="d-flex justify-content-between align-items-center gap-2">
                            <div class="network-name">{{ $brand->name }}</div>
                            @if ($isCurrent)
                                <span class="badge network-badge"><i class="bi bi-check2"></i> In use</span>
                            @endif
                        </div>
                        <div class="network-full">{{ $brand->fullName }}</div>

                        {{-- A miniature of the app in this network's colours --}}
                        <div class="network-preview" aria-hidden="true">
                            <div class="np-side">
                                <span class="np-logo"></span>
                                <span class="np-item is-active"></span>
                                <span class="np-item"></span>
                                <span class="np-item"></span>
                                <span class="np-item"></span>
                            </div>
                            <div class="np-main">
                                <div class="np-stripe"></div>
                                <div class="np-board">
                                    <span>280</span><span>290</span><span>300</span>
                                </div>
                                <div class="np-tiles"><span></span><span></span><span></span></div>
                                <span class="np-button"></span>
                            </div>
                        </div>

                        <div class="network-swatches" aria-hidden="true">
                            @foreach ($brand->stripe as $color)
                                <span style="background: {{ $color }}"></span>
                            @endforeach
                        </div>
                    </div>

                    <button type="submit" class="network-cta">
                        {{ $isCurrent ? 'Continue with '.$brand->name : 'Use '.$brand->name.' theme' }}
                    </button>
                </form>
            @endforeach
        </div>

        <p class="small text-white-50 mt-5 mb-0">
            Company names and logos belong to their owners and are shown here only to theme this dealer software.
        </p>
    </div>
</x-layouts.guest>
