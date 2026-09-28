<x-layouts.guest title="Login">
    <div class="auth-card">
        <div class="text-center text-white mb-4">
            <div class="app-brand-mark app-brand-mark-lg mx-auto mb-3">
                <i class="bi bi-fuel-pump-fill fs-4"></i>
            </div>
            <h1 class="h3 fw-bold mb-1">FuelTrack Lite</h1>
            <p class="text-white-50 mb-0">{{ config('fueltrack.station.name') }}</p>
        </div>

        <div class="card border-0 shadow-lg">
            <div class="card-body p-4">
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
                            <button type="button" class="btn btn-outline-secondary" @click="show = !show"
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
                        <label class="form-check-label" for="remember">Remember me</label>
                    </div>

                    <button type="submit" class="btn btn-warning btn-lg w-100 fw-semibold" :disabled="submitting">
                        <span x-show="submitting" x-cloak class="spinner-border spinner-border-sm me-2"></span>
                        Login
                    </button>
                </form>
            </div>
        </div>

        @if (config('fueltrack.show_demo_credentials'))
            <div class="alert alert-light border-0 small mt-3 mb-0">
                <div class="fw-semibold mb-1"><i class="bi bi-info-circle me-1"></i> Demo accounts</div>
                Owner: <code>owner@alnoor.com</code><br>
                Staff: <code>staff@alnoor.com</code><br>
                Password: <code>Demo@12345</code>
            </div>
        @endif
    </div>
</x-layouts.guest>
