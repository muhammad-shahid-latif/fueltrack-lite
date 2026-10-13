@props(['title' => null, 'subtitle' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Turbo: no snapshot cache (Alpine state must start fresh), keep scroll when a form returns to the same page. --}}
    <meta name="turbo-cache-control" content="no-cache">
    <meta name="turbo-refresh-scroll" content="preserve">
    <title>{{ $title ? $title.' · ' : '' }}{{ \App\Support\Brand::current()?->name ? \App\Support\Brand::current()->name.' · ' : '' }}{{ config('app.name') }}</title>
    @include('partials.fonts')
    @include('partials.brand-theme')
    @vite('resources/js/app.js')
</head>
<body>
    <div class="brand-stripe d-print-none" aria-hidden="true"></div>
    <div class="d-lg-flex min-vh-100">
        @include('partials.sidebar')

        <div class="flex-grow-1 d-flex flex-column" style="min-width: 0;">
            <header class="app-topbar sticky-top d-print-none">
                <div class="d-flex align-items-center gap-3 px-3 px-lg-4 py-3">
                    <button class="btn btn-light d-lg-none" type="button"
                            data-bs-toggle="offcanvas" data-bs-target="#appSidebar"
                            aria-controls="appSidebar" aria-label="Open menu">
                        <i class="bi bi-list fs-5"></i>
                    </button>

                    @php($currentPage = \App\Support\Navigation::current(auth()->user()))
                    <span class="page-cube d-none d-sm-grid" aria-hidden="true"><i class="bi bi-{{ $currentPage['icon'] ?? 'fuel-pump-fill' }}"></i></span>

                    <div style="min-width: 0;">
                        <h1 class="mb-0 text-truncate">{{ $title }}</h1>
                        @if ($subtitle)
                            <div class="small text-secondary text-truncate">{{ $subtitle }}</div>
                        @endif
                    </div>

                    <div class="ms-auto dropdown">
                        <button class="btn app-user d-flex align-items-center gap-2 border-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="app-user-avatar">{{ str(auth()->user()->name)->substr(0, 1)->upper() }}</span>
                            <span class="d-none d-sm-inline text-start lh-sm">
                                <span class="d-block small fw-semibold">{{ auth()->user()->name }}</span>
                                <span class="d-block text-secondary" style="font-size: .74rem;">{{ auth()->user()->role->label() }}</span>
                            </span>
                            <i class="bi bi-chevron-down small text-secondary"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-4 p-2">
                            <li class="dropdown-item-text small text-secondary">{{ auth()->user()->email }}</li>
                            <li><hr class="dropdown-divider"></li>
                            @unless (\App\Support\Brand::locked())
                                <li>
                                    <a href="{{ route('brands.index') }}" class="dropdown-item rounded-3">
                                        <i class="bi bi-palette2 me-2"></i>Change fuel network
                                    </a>
                                </li>
                            @endunless
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item rounded-3 text-danger">
                                        <i class="bi bi-box-arrow-right me-2"></i>Log out
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            </header>

            <main class="flex-grow-1 p-3 p-lg-4 app-main">
                {{ $slot }}
            </main>

            <footer class="px-3 px-lg-4 py-3 small text-secondary d-print-none">
                {{ config('app.name') }} for {{ \App\Support\Station::name() }}@if (\App\Support\Brand::current()) · {{ \App\Support\Brand::current()->fullName }} dealer @endif
            </footer>
        </div>
    </div>

    @include('partials.flash')
</body>
</html>
