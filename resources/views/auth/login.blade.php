@php($brand = \App\Support\Brand::current())

<x-layouts.guest title="Log in">
    <div class="auth-stripe" aria-hidden="true"></div>
    <div class="container-xxl min-vh-100 d-flex align-items-center py-4">
        <div class="row w-100 g-5 align-items-center justify-content-center mx-0">
            <div class="col-lg-6 d-none d-lg-block text-white">
                <x-brand-logo size="xl" class="mb-4 auth-logo-float" />

                <h1 class="auth-headline mb-3">Every liter, every rupee, every shift.</h1>
                <p class="fs-5 text-white-50 mb-4" style="max-width: 44ch;">
                    Meter readings, stock, staff salaries and the daily cash count for {{ \App\Support\Station::name() }}, in one place.
                </p>
                @if ($brand)
                    <span class="auth-network-chip"><i class="bi bi-patch-check-fill"></i> {{ $brand->fullName }} dealer</span>
                @endif

                <div class="auth-art mt-4" aria-hidden="true">
                    @include('auth.partials.dispenser-art')
                </div>
            </div>

            <div class="col-lg-5 col-xl-4">
                <div class="text-center text-white mb-4 d-lg-none">
                    <x-brand-logo size="lg" class="mx-auto mb-3 auth-logo-float" />
                    <div class="app-brand-name fs-4">{{ \App\Support\Station::name() }}</div>
                    @if ($brand)
                        <div class="text-white-50">{{ $brand->fullName }} dealer</div>
                    @endif
                </div>

                <div class="card auth-card mx-auto">
                    <div class="card-body p-4 p-md-5">
                        <h2 class="h3 fw-bold mb-1">Log in</h2>
                        <p class="text-secondary mb-4">{{ \App\Support\Station::name() }}</p>

                        <form method="POST" action="{{ route('login.store') }}" x-data="{ submitting: false }" @submit="submitting = true" novalidate>
                            @csrf

                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input id="email" type="email" name="email" value="{{ old('email') }}"
                                       class="form-control form-control-lg @error('email') is-invalid @enderror"
                                       autocomplete="username" required autofocus>
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3" x-data="{ show: false }">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group has-validation">
                                    <input id="password" :type="show ? 'text' : 'password'" type="password" name="password"
                                           class="form-control form-control-lg @error('password') is-invalid @enderror"
                                           autocomplete="current-password" required>
                                    <button type="button" class="btn btn-light" @click="show = !show"
                                            :aria-label="show ? 'Hide password' : 'Show password'">
                                        <i class="bi" :class="show ? 'bi-eye-slash' : 'bi-eye'"></i>
                                    </button>
                                    @error('password')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" name="remember" id="remember" @checked(old('remember'))>
                                <label class="form-check-label" for="remember">Keep me logged in</label>
                            </div>

                            <button type="submit" class="btn btn-warning btn-lg w-100" :disabled="submitting">
                                <span x-show="submitting" x-cloak class="spinner-border spinner-border-sm me-2"></span>
                                Log in
                            </button>
                        </form>

                        @if (config('fueltrack.show_demo_credentials'))
                            <div class="rounded-4 bg-body-tertiary small mt-4 p-3">
                                <div class="fw-semibold mb-1"><i class="bi bi-info-circle me-1"></i> Demo accounts</div>
                                Owner: <code>owner@alnoor.com</code><br>
                                Staff: <code>staff@alnoor.com</code><br>
                                Password: <code>Demo@12345</code>
                            </div>
                        @endif

                        @unless (\App\Support\Brand::locked())
                            <div class="d-flex justify-content-between align-items-center border-top mt-4 pt-3 small">
                                <span class="text-secondary">Fuel network: <strong class="text-body">{{ $brand?->name ?? 'not chosen' }}</strong></span>
                                <a href="{{ route('brands.index') }}" class="fw-semibold text-decoration-none"><i class="bi bi-palette2 me-1"></i>{{ $brand ? 'Change' : 'Choose' }}</a>
                            </div>
                        @endunless
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.guest>
