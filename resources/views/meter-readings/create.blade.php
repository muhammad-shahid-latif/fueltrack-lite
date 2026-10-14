<x-layouts.app title="New meter reading" subtitle="Liters and amount are calculated as you type">
    @if ($todayIsClosed)
        <div class="alert alert-warning d-flex gap-2 align-items-center">
            <i class="bi bi-lock-fill"></i> Today is already closed, so no more readings can be saved.
        </div>
    @endif

    <div class="row justify-content-center">
        <div class="col-lg-9 col-xl-7">
            <div class="card">
                <div class="card-body p-4 p-md-5">
                    <form method="POST" action="{{ route('meter-readings.store') }}" novalidate
                          x-data="meterReadingForm(@js($nozzles), @js(old('nozzle_id', $selectedNozzleId)), @js(old('closing_meter', '')))"
                          @submit="submitting = true">
                        @csrf

                        <div class="mb-4">@include('partials.business-date', ['date' => $businessDate])</div>

                        <div class="row g-4">
                            <div class="col-12">
                                <label for="nozzle_id" class="form-label">Pump and nozzle</label>
                                <select id="nozzle_id" name="nozzle_id" x-model.number="nozzleId" @change="onNozzleChange()"
                                        class="form-select form-select-lg @error('nozzle_id') is-invalid @enderror" required>
                                    <option value="">Choose a nozzle</option>
                                    <template x-for="nozzle in nozzles" :key="nozzle.id">
                                        <option :value="nozzle.id" :selected="nozzle.id === nozzleId" x-text="`${nozzle.label} — ${nozzle.fuel}`"></option>
                                    </template>
                                </select>
                                @error('nozzle_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12">
                                <span class="form-label d-block">Shift</span>
                                <div class="row g-2 shift-picker" role="radiogroup" aria-label="Shift">
                                    @foreach ($shifts as $shift)
                                        <div class="col-{{ intdiv(12, max(1, count($shifts))) }}">
                                            <input type="radio" class="btn-check" name="shift" id="shift_{{ $shift->value }}" value="{{ $shift->value }}"
                                                   @checked(old('shift', $currentShift->value) === $shift->value)>
                                            <label class="btn btn-outline-dark w-100" for="shift_{{ $shift->value }}">
                                                {{ $shift->label() }}
                                                <small>{{ $shift->hours() }}</small>
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                                @error('shift') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-sm-6">
                                <label for="opening_meter" class="form-label">Opening meter</label>
                                <input type="text" inputmode="decimal" id="opening_meter" name="opening_meter"
                                       x-model="opening" readonly
                                       class="form-control form-control-lg ft-num @error('opening_meter') is-invalid @enderror">
                                <div class="form-text">Carried over from the last closing reading.</div>
                                @error('opening_meter') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-sm-6">
                                <label for="closing_meter" class="form-label">Closing meter</label>
                                <input type="text" inputmode="decimal" id="closing_meter" name="closing_meter"
                                       x-model="closing" x-ref="closing" autocomplete="off" placeholder="0.00"
                                       class="form-control form-control-lg ft-num @error('closing_meter') is-invalid @enderror"
                                       :class="{ 'is-invalid': closing !== '' && liters <= 0 }" required>
                                @error('closing_meter')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @else
                                    <div class="invalid-feedback">Closing meter must be greater than the opening meter.</div>
                                @enderror
                            </div>

                            <div class="col-12">
                                <div class="calc-panel">
                                    <div class="row text-center g-2">
                                        <div class="col-{{ intdiv(12, max(1, count($shifts))) }}">
                                            <div class="calc-label">Liters sold</div>
                                            <div class="calc-value" x-text="liters > 0 ? formatNumber(liters) + ' L' : '—'"></div>
                                        </div>
                                        <div class="col-{{ intdiv(12, max(1, count($shifts))) }}">
                                            <div class="calc-label">Rate</div>
                                            <div class="calc-value" x-text="selected ? 'Rs. ' + formatNumber(selected.rate) : '—'"></div>
                                        </div>
                                        <div class="col-{{ intdiv(12, max(1, count($shifts))) }}">
                                            <div class="calc-label">Sales amount</div>
                                            <div class="calc-value is-total" x-text="liters > 0 ? 'Rs. ' + formatNumber(amount) : '—'"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <label for="notes" class="form-label">Notes <span class="text-secondary fw-normal small">(optional)</span></label>
                                <input type="text" id="notes" name="notes" value="{{ old('notes') }}" maxlength="500"
                                       class="form-control @error('notes') is-invalid @enderror">
                                @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('meter-readings.index') }}" class="btn btn-light">Cancel</a>
                            <button type="submit" class="btn btn-warning px-4" :disabled="submitting || liters <= 0">
                                <span x-show="submitting" x-cloak class="spinner-border spinner-border-sm me-2"></span>
                                Save reading
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function meterReadingForm(nozzles, nozzleId, closing) {
            return {
                nozzles,
                nozzleId: nozzleId ? Number(nozzleId) : null,
                opening: '',
                closing: closing ?? '',
                submitting: false,

                init() {
                    this.syncOpening();
                },

                get selected() {
                    return this.nozzles.find((nozzle) => nozzle.id === this.nozzleId) ?? null;
                },

                get liters() {
                    const value = parseFloat(this.closing) - parseFloat(this.opening);

                    return Number.isFinite(value) ? Math.round(value * 100) / 100 : 0;
                },

                get amount() {
                    return this.selected ? Math.round(this.liters * this.selected.rate * 100) / 100 : 0;
                },

                onNozzleChange() {
                    this.syncOpening();
                    this.closing = '';
                    this.$nextTick(() => this.$refs.closing.focus());
                },

                syncOpening() {
                    this.opening = this.selected ? this.selected.current_meter : '';
                },

                formatNumber(value) {
                    const number = Number(value);
                    const digits = Number.isInteger(number) ? 0 : 2;

                    return number.toLocaleString('en-US', { minimumFractionDigits: digits, maximumFractionDigits: 2 });
                },
            };
        }
    </script>
</x-layouts.app>
