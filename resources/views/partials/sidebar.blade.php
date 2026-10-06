@php($navigation = \App\Support\Navigation::for(auth()->user()))

<div class="app-sidebar-wrap d-print-none">
    <aside class="offcanvas-lg offcanvas-start app-sidebar h-100" data-bs-theme="dark" tabindex="-1" id="appSidebar" aria-labelledby="appSidebarLabel">
        <div class="offcanvas-header d-lg-none">
            <span class="offcanvas-title text-white fw-semibold" id="appSidebarLabel">Menu</span>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#appSidebar" aria-label="Close"></button>
        </div>

        <div class="offcanvas-body d-flex flex-column p-3 w-100 h-100">
            <a href="{{ route('dashboard') }}" class="app-brand d-flex align-items-center gap-2 text-decoration-none">
                <span class="app-brand-mark"><i class="bi bi-fuel-pump-fill"></i></span>
                <span style="min-width: 0;">
                    <span class="app-brand-name d-block">FuelTrack</span>
                    <span class="d-block small text-white-50 text-truncate" style="max-width: 175px;">{{ \App\Support\Station::name() }}</span>
                </span>
            </a>

            <nav class="app-nav" aria-label="Main">
                @foreach ($navigation as $group)
                    @if ($group['heading'])
                        <div class="app-nav-heading">{{ $group['heading'] }}</div>
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
                                    </span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endforeach
            </nav>

            <div class="app-sidebar-clock mt-4" title="Entries are dated by the server clock">
                Business date (server)
                <strong>{{ \App\Support\BusinessClock::now()->format('d M Y') }}</strong>
            </div>
        </div>
    </aside>
</div>
