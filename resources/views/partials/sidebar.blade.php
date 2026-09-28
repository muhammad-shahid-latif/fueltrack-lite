@php($navigation = \App\Support\Navigation::for(auth()->user()))

<div class="app-sidebar-wrap">
    <aside class="offcanvas-lg offcanvas-start app-sidebar" data-bs-theme="dark" tabindex="-1" id="appSidebar" aria-labelledby="appSidebarLabel">
        <div class="offcanvas-header d-lg-none">
            <span class="offcanvas-title text-white fw-semibold" id="appSidebarLabel">Menu</span>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#appSidebar" aria-label="Close"></button>
        </div>

        <div class="offcanvas-body d-flex flex-column p-3 w-100">
            <a href="{{ route('dashboard') }}" class="d-flex align-items-center gap-2 text-decoration-none text-white mb-2 px-2">
                <span class="app-brand-mark"><i class="bi bi-fuel-pump-fill"></i></span>
                <span class="lh-sm">
                    <span class="d-block fw-bold">FuelTrack Lite</span>
                    <span class="d-block small text-white-50 text-truncate" style="max-width: 170px;">{{ config('fueltrack.station.name') }}</span>
                </span>
            </a>

            <nav class="app-nav">
                @foreach ($navigation as $group)
                    @if ($group['heading'])
                        <div class="app-nav-heading">{{ $group['heading'] }}</div>
                    @else
                        <div class="mt-2"></div>
                    @endif

                    <ul class="nav flex-column gap-1">
                        @foreach ($group['items'] as $item)
                            <li class="nav-item">
                                @if ($item['enabled'])
                                    <a href="{{ route($item['route']) }}"
                                       @class(['nav-link', 'active' => request()->routeIs($item['active'])])
                                       @if (request()->routeIs($item['active'])) aria-current="page" @endif>
                                        <i class="bi bi-{{ $item['icon'] }}"></i>
                                        <span>{{ $item['label'] }}</span>
                                    </a>
                                @else
                                    <span class="nav-link disabled" aria-disabled="true">
                                        <i class="bi bi-{{ $item['icon'] }}"></i>
                                        <span>{{ $item['label'] }}</span>
                                        <span class="badge text-bg-secondary ms-auto fw-normal">Soon</span>
                                    </span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endforeach
            </nav>
        </div>
    </aside>
</div>
