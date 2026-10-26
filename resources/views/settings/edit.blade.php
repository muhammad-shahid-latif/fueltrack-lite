<x-layouts.app title="Settings" subtitle="Station details used across the app and on reports">
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">Station details</div>
                <div class="card-body p-4 p-md-5">
                    <form method="POST" action="{{ route('settings.update') }}" novalidate>
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="station_name" class="form-label">Station name</label>
                            <input type="text" id="station_name" name="station_name" maxlength="100"
                                   value="{{ old('station_name', $settings['station_name']) }}"
                                   class="form-control @error('station_name') is-invalid @enderror" required>
                            @error('station_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label for="station_address" class="form-label">Address</label>
                            <input type="text" id="station_address" name="station_address" maxlength="255"
                                   value="{{ old('station_address', $settings['station_address']) }}"
                                   class="form-control @error('station_address') is-invalid @enderror" required>
                            @error('station_address') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <label for="station_phone" class="form-label">Phone</label>
                                <input type="tel" id="station_phone" name="station_phone" maxlength="30"
                                       value="{{ old('station_phone', $settings['station_phone']) }}"
                                       class="form-control @error('station_phone') is-invalid @enderror" placeholder="03XX-XXXXXXX">
                                @error('station_phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-sm-6">
                                <label for="currency" class="form-label">Currency</label>
                                <input type="text" id="currency" value="{{ config('fueltrack.currency') }}" class="form-control" disabled>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="default_opening_cash" class="form-label">Default opening cash (Rs.)</label>
                            <input type="text" inputmode="decimal" id="default_opening_cash" name="default_opening_cash"
                                   value="{{ old('default_opening_cash', $settings['default_opening_cash']) }}"
                                   class="form-control @error('default_opening_cash') is-invalid @enderror" required>
                            <div class="form-text">Pre-filled as the cash float on the daily closing form.</div>
                            @error('default_opening_cash') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-warning px-4">Save settings</button>
                        </div>
                    </form>
                </div>
            </div>

            @php($resetOpen = $errors->hasAny(['scope', 'confirm', 'password']))
            <form method="POST" action="{{ route('settings.reset') }}" novalidate class="card danger-zone mt-4"
                  x-data="{ open: @js($resetOpen), scope: @js(old('scope', 'records')), confirm: '', password: '', sending: false }"
                  @submit="if (confirm !== 'RESET' || password === '') { $event.preventDefault(); return; } sending = true">
                @csrf
                <div class="card-header d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-octagon-fill text-danger"></i>
                    <span class="me-auto">Reset data</span>
                    <button type="button" class="btn btn-sm btn-outline-danger" x-show="! open" @click="open = true">Start over…</button>
                </div>
                <div class="card-body p-4">
                    <p class="small text-secondary mb-0" x-show="! open">Erase the demo data, or every record, to start using FuelTrack for real. Logins and settings are always kept.</p>

                    <template x-if="open">
                        <div>
                            <div class="vstack gap-2 mb-3">
                                <label class="reset-option" :class="scope === 'records' && 'active'">
                                    <input type="radio" name="scope" value="records" x-model="scope" class="form-check-input mt-1">
                                    <span>
                                        <strong class="d-block">Erase records, keep station setup</strong>
                                        <span class="small text-secondary">Deletes meter readings, sales, khata, payments, expenses, closings, deliveries, payroll, fuel relief, the audit trail, and all customers, suppliers and staff. <strong>Keeps</strong> fuel products and prices, tanks, pumps and nozzles. Today's stock and meter positions carry over.</span>
                                    </span>
                                </label>
                                <label class="reset-option" :class="scope === 'everything' && 'active'">
                                    <input type="radio" name="scope" value="everything" x-model="scope" class="form-check-input mt-1">
                                    <span>
                                        <strong class="d-block">Erase everything (fresh start)</strong>
                                        <span class="small text-secondary">All of the above plus fuel products, prices, tanks, pumps and nozzles. Only logins and settings remain. Use this to remove the demo completely.</span>
                                    </span>
                                </label>
                            </div>
                            @error('scope') <div class="small text-danger mb-2">{{ $message }}</div> @enderror

                            <div class="alert alert-danger small d-flex gap-2 mb-3">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                <span>This cannot be undone. Take a backup of the database first if any of this data matters.</span>
                            </div>

                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <label for="reset_confirm" class="form-label small">Type <strong>RESET</strong> to confirm</label>
                                    <input type="text" id="reset_confirm" name="confirm" x-model="confirm" autocomplete="off" spellcheck="false"
                                           class="form-control ft-num @error('confirm') is-invalid @enderror">
                                    @error('confirm') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-sm-6">
                                    <label for="reset_password" class="form-label small">Your password</label>
                                    <input type="password" id="reset_password" name="password" x-model="password" autocomplete="current-password"
                                           class="form-control @error('password') is-invalid @enderror">
                                    @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div class="d-flex flex-wrap gap-2 justify-content-end mt-4">
                                <button type="button" class="btn btn-light" @click="open = false; confirm = ''; password = ''">Cancel</button>
                                <button type="submit" class="btn btn-danger px-4" :disabled="sending || confirm !== 'RESET' || password === ''">
                                    <i class="bi bi-trash3 me-1"></i><span x-text="scope === 'records' ? 'Erase records' : 'Erase everything'"></span>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </form>
        </div>

        <div class="col-lg-5">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    Current prices
                    <a href="{{ route('fuel-products.index') }}" class="small fw-normal text-decoration-none">All products</a>
                </div>
                <ul class="list-group list-group-flush">
                    @foreach ($products as $product)
                        <li class="list-group-item d-flex justify-content-between align-items-center px-4 py-3 {{ \App\Support\Grade::cssClass($product) }}">
                            <span>
                                <span class="d-flex align-items-center gap-2 fw-semibold"><span class="grade-dot"></span>{{ $product->name }}</span>
                                @if ($product->latestPrice)
                                    <span class="small text-secondary">since {{ $product->latestPrice->effective_at->format('d M Y, h:i A') }}</span>
                                @endif
                            </span>
                            <span class="d-flex align-items-center gap-2">
                                <span class="ft-num fw-bold">@money($product->selling_price) / {{ $product->unit->value }}</span>
                                <a href="{{ route('fuel-products.edit', $product) }}" class="btn btn-sm btn-outline-dark" aria-label="Change {{ $product->name }} price">
                                    <i class="bi bi-pencil"></i>
                                </a>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</x-layouts.app>
