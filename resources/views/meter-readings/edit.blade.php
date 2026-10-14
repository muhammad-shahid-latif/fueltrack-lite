<x-layouts.app title="Edit meter reading" subtitle="The sale is recalculated at the rate it was sold at">
    @php($nozzle = $reading->nozzle)

    <div class="row justify-content-center">
        <div class="col-lg-9 col-xl-7">
            <div class="card mb-4">
                <div class="card-body p-4 p-md-5">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
                        <div class="d-flex align-items-center gap-3 {{ \App\Support\Grade::cssClass($nozzle->fuelProduct) }}">
                            <span class="grade-dot"></span>
                            <div>
                                <div class="fw-bold fs-5">{{ $nozzle->label() }}</div>
                                <div class="small text-secondary">{{ $nozzle->fuelProduct->name }} · {{ $reading->business_date->format('D, d M Y') }}</div>
                            </div>
                        </div>
                        <span class="small text-secondary text-sm-end">
                            Recorded by {{ $reading->recorder?->name ?? '—' }}<br>{{ $reading->created_at->format('d M Y, h:i A') }}
                        </span>
                    </div>

                    <form method="POST" action="{{ route('meter-readings.update', $reading) }}" novalidate
                          x-data="readingEdit({
                              opening: @js(old('opening_meter', \App\Support\Format::meter($reading->opening_meter))),
                              closing: @js(old('closing_meter', \App\Support\Format::meter($reading->closing_meter))),
                              rate: {{ (float) $reading->sale->rate }},
                              nextClosing: @js($next ? (float) $next->closing_meter : null),
                              nextOpening: @js($next ? (float) $next->opening_meter : null),
                          })"
                          @submit="submitting = true">
                        @csrf
                        @method('PUT')

                        <div class="row g-4">
                            <div class="col-12">
                                <span class="form-label d-block">Shift</span>
                                <div class="row g-2 shift-picker" role="radiogroup" aria-label="Shift">
                                    @foreach ($shifts as $shift)
                                        <div class="col-{{ intdiv(12, max(1, count($shifts))) }}">
                                            <input type="radio" class="btn-check" name="shift" id="shift_{{ $shift->value }}" value="{{ $shift->value }}"
                                                   @checked(old('shift', $reading->shift->value) === $shift->value)>
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
                                <input type="text" inputmode="decimal" id="opening_meter" name="opening_meter" x-model="opening"
                                       @readonly($previous)
                                       class="form-control form-control-lg ft-num @error('opening_meter') is-invalid @enderror">
                                @error('opening_meter')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @else
                                    <div class="form-text">
                                        @if ($previous)
                                            Fixed to the previous closing reading ({{ $previous->business_date->format('d M') }}, {{ $previous->shift->label() }}).
                                        @else
                                            First reading of this nozzle, so the opening can be corrected.
                                        @endif
                                    </div>
                                @enderror
                            </div>

                            <div class="col-sm-6">
                                <label for="closing_meter" class="form-label">Closing meter</label>
                                <input type="text" inputmode="decimal" id="closing_meter" name="closing_meter" x-model="closing" autocomplete="off"
                                       class="form-control form-control-lg ft-num @error('closing_meter') is-invalid @enderror"
                                       :class="{ 'is-invalid': closing !== '' && problem }" required>
                                @error('closing_meter')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @else
                                    <div class="invalid-feedback" x-text="problem"></div>
                                @enderror
                            </div>

                            @if ($next)
                                <div class="col-12" x-show="changesNext" x-cloak>
                                    <div class="alert alert-info small mb-0">
                                        <i class="bi bi-arrow-down-circle me-1"></i>
                                        The next reading ({{ $next->business_date->format('d M') }}, {{ $next->shift->label() }}) will start from this closing meter,
                                        and its sale will be recalculated too.
                                    </div>
                                </div>
                            @endif

                            <div class="col-12">
                                <div class="calc-panel">
                                    <div class="row text-center g-2">
                                        <div class="col-{{ intdiv(12, max(1, count($shifts))) }}">
                                            <div class="calc-label">Liters sold</div>
                                            <div class="calc-value" x-text="liters > 0 ? formatNumber(liters) + ' L' : '—'"></div>
                                        </div>
                                        <div class="col-{{ intdiv(12, max(1, count($shifts))) }}">
                                            <div class="calc-label">Rate sold at</div>
                                            <div class="calc-value" x-text="'Rs. ' + formatNumber(rate)"></div>
                                        </div>
                                        <div class="col-{{ intdiv(12, max(1, count($shifts))) }}">
                                            <div class="calc-label">Sales amount</div>
                                            <div class="calc-value is-total" x-text="liters > 0 ? 'Rs. ' + formatNumber(amount) : '—'"></div>
                                        </div>
                                    </div>
                                    <div class="small text-center mt-2 opacity-75">
                                        Was @liters($reading->liters) = @money($reading->sale->amount)
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <label for="notes" class="form-label">Notes <span class="text-secondary fw-normal small">(optional)</span></label>
                                <input type="text" id="notes" name="notes" value="{{ old('notes', $reading->notes) }}" maxlength="500"
                                       class="form-control @error('notes') is-invalid @enderror" placeholder="e.g. Reason for the correction">
                                @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('meter-readings.index', ['date' => $reading->business_date->toDateString()]) }}" class="btn btn-light">Cancel</a>
                            <button type="submit" class="btn btn-warning px-4" :disabled="submitting || problem !== ''">
                                <span x-show="submitting" x-cloak class="spinner-border spinner-border-sm me-2"></span>
                                Save changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card border-danger-subtle">
                <div class="card-body p-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div>
                        <div class="fw-semibold">Delete this reading</div>
                        @if ($next)
                            <p class="small text-secondary mb-0">
                                Only the latest reading of a nozzle can be deleted. Delete the reading from
                                {{ $next->business_date->format('d M Y') }} ({{ $next->shift->label() }}) first.
                            </p>
                        @else
                            <p class="small text-secondary mb-0">
                                Removes its sale of @money($reading->sale->amount) and puts {{ $nozzle->label() }} back to @meter($reading->opening_meter).
                            </p>
                        @endif
                    </div>
                    @unless ($next)
                        <form method="POST" action="{{ route('meter-readings.destroy', $reading) }}" x-data="{ sure: false }">
                            @csrf
                            @method('DELETE')
                            <button type="button" class="btn btn-outline-danger" x-show="! sure" @click="sure = true">
                                <i class="bi bi-trash3 me-1"></i> Delete reading
                            </button>
                            <span x-show="sure" x-cloak><span class="d-flex flex-wrap align-items-center gap-2">
                                <button type="submit" class="btn btn-danger">Yes, delete</button>
                                <button type="button" class="btn btn-light" @click="sure = false">Keep it</button>
                            </span></span>
                        </form>
                    @endunless
                </div>
            </div>
        </div>
    </div>

    <script>
        function readingEdit(initial) {
            return {
                ...initial,
                submitting: false,

                get liters() {
                    const value = parseFloat(this.closing) - parseFloat(this.opening);

                    return Number.isFinite(value) ? Math.round(value * 100) / 100 : 0;
                },

                get amount() {
                    return Math.round(this.liters * this.rate * 100) / 100;
                },

                get changesNext() {
                    return this.nextOpening !== null && Math.abs(parseFloat(this.closing) - this.nextOpening) >= 0.005;
                },

                get problem() {
                    if (this.liters <= 0) {
                        return 'Closing meter must be greater than the opening meter.';
                    }

                    if (this.nextClosing !== null && parseFloat(this.closing) >= this.nextClosing) {
                        return 'Closing meter must stay below the next reading\'s closing meter (' + this.nextClosing.toFixed(2) + ').';
                    }

                    return '';
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
