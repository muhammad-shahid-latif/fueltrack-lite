@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    @vite('resources/js/app.js')
</head>
<body>
    <div class="d-lg-flex min-vh-100">
        @include('partials.sidebar')

        <div class="flex-grow-1 d-flex flex-column" style="min-width: 0;">
            <header class="app-topbar sticky-top">
                <div class="d-flex align-items-center gap-2 px-3 px-lg-4 py-2">
                    <button class="btn btn-light d-lg-none" type="button"
                            data-bs-toggle="offcanvas" data-bs-target="#appSidebar"
                            aria-controls="appSidebar" aria-label="Open menu">
                        <i class="bi bi-list fs-5"></i>
                    </button>

                    <h1 class="h5 mb-0 fw-semibold text-truncate">{{ $title }}</h1>

                    <div class="ms-auto dropdown">
                        <button class="btn btn-light d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle fs-5"></i>
                            <span class="d-none d-sm-inline text-start lh-sm">
                                <span class="d-block small fw-semibold">{{ auth()->user()->name }}</span>
                                <span class="d-block text-muted" style="font-size: .75rem;">{{ auth()->user()->role->label() }}</span>
                            </span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                            <li class="dropdown-item-text small text-muted">{{ auth()->user()->email }}</li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item text-danger">
                                        <i class="bi bi-box-arrow-right me-2"></i>Logout
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            </header>

            <main class="flex-grow-1 p-3 p-lg-4">
                @include('partials.flash')

                {{ $slot }}
            </main>

            <footer class="px-3 px-lg-4 py-3 small text-muted border-top bg-white">
                {{ config('app.name') }} · {{ config('fueltrack.station.name') }}
            </footer>
        </div>
    </div>
</body>
</html>
