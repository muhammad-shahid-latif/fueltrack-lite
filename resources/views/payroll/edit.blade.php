<x-layouts.app :title="'Adjust salary · '.$slip->employee->name" :subtitle="$slip->month->format('F Y').' · '.$slip->days_in_month.' days'">
    <div class="row justify-content-center">
        <div class="col-lg-10 col-xl-8">
            <form method="POST" action="{{ route('payroll.slips.update', $slip) }}" novalidate
                  x-data="slipForm({
                      basic: {{ (float) $slip->basic_salary }},
                      days: {{ $slip->days_in_month }},
                      absent: @js(old('absent_days', \App\Support\Format::input($slip->absent_days))),
                      bonus: @js(old('bonus', \App\Support\Format::input($slip->bonus))),
                      other: @js(old('other_deduction', \App\Support\Format::input($slip->other_deduction))),
                      advance: @js(old('advance_deduction', \App\Support\Format::input($slip->advance_deduction))),
                  })"
                  @submit="submitting = true">
                @csrf
                @method('PUT')

                <div class="row g-4">
                    <div class="col-md-7">
                        <div class="card h-100">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-center gap-3 mb-4">
                                    <x-employee-avatar :employee="$slip->employee" />
                                    <div>
                                        <div class="fw-bold">{{ $slip->employee->name }}</div>
                                        <div class="small text-secondary">{{ $slip->employee->designation }} · basic @money($slip->basic_salary)</div>
                                    </div>
                                </div>

                                <div class="row g-3">
                                    <div class="col-sm-6">
                                        <label for="absent_days" class="form-label">Days absent</label>
                                        <input type="text" inputmode="decimal" id="absent_days" name="absent_days" x-model="absent"
                                               class="form-control ft-num @error('absent_days') is-invalid @enderror">
                                        @error('absent_days')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @else
                                            <div class="form-text">Half days allowed, e.g. 1.5. One day = <span x-text="money(dayRate)"></span>.</div>
                                        @enderror
                                    </div>
                                    <div class="col-sm-6">
                                        <label for="bonus" class="form-label">Bonus or overtime</label>
                                        <input type="text" inputmode="decimal" id="bonus" name="bonus" x-model="bonus"
                                               class="form-control ft-num @error('bonus') is-invalid @enderror">
                                        @error('bonus') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="col-sm-6">
                                        <label for="advance_deduction" class="form-label">Advance recovery</label>
                                        <input type="text" inputmode="decimal" id="advance_deduction" name="advance_deduction" x-model="advance"
                                               class="form-control ft-num @error('advance_deduction') is-invalid @enderror">
                                        @error('advance_deduction')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @else
                                            <div class="form-text d-flex justify-content-between gap-2">
                                                <span>Owed: @money($outstanding)</span>
                                                @if ($outstanding > 0)
                                                    <button type="button" class="btn btn-link btn-sm p-0" @click="advance = '{{ \App\Support\Format::number($outstanding) }}'.replace(/,/g, '')">Deduct all</button>
                                                @endif
                                            </div>
                                        @enderror
                                    </div>
                                    <div class="col-sm-6">
                                        <label for="other_deduction" class="form-label">Other deduction</label>
                                        <input type="text" inputmode="decimal" id="other_deduction" name="other_deduction" x-model="other"
                                               class="form-control ft-num @error('other_deduction') is-invalid @enderror">
                                        @error('other_deduction')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @else
                                            <div class="form-text">e.g. cash shortage or damage</div>
                                        @enderror
                                    </div>
                                    <div class="col-12">
                                        <label for="notes" class="form-label">Notes <span class="text-secondary fw-normal small">(optional, shown on the slip)</span></label>
                                        <input type="text" id="notes" name="notes" value="{{ old('notes', $slip->notes) }}" maxlength="500"
                                               class="form-control @error('notes') is-invalid @enderror" placeholder="e.g. Eid bonus, 2 days leave">
                                        @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-5">
                        <div class="money-panel p-4 h-100 d-flex flex-column">
                            <table class="table table-sm table-borderless mb-3" style="--bs-table-bg: transparent; --bs-table-color: #fff;">
                                <tr><td class="opacity-75">Basic salary</td><td class="text-end" x-text="money(basic)"></td></tr>
                                <tr><td class="opacity-75">− Absence</td><td class="text-end" x-text="money(-absence)"></td></tr>
                                <tr><td class="opacity-75">+ Bonus</td><td class="text-end" x-text="money(num(bonus))"></td></tr>
                                <tr><td class="opacity-75">− Advance</td><td class="text-end" x-text="money(-num(advance))"></td></tr>
                                <tr><td class="opacity-75">− Other</td><td class="text-end" x-text="money(-num(other))"></td></tr>
                            </table>
                            <div class="mt-auto">
                                <div class="small opacity-75">Net salary to pay</div>
                                <div class="money-panel-amount" x-text="money(net)"></div>
                                <div class="small text-warning mt-1" x-show="net < 0" x-cloak>Deductions are more than the salary.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2 justify-content-end mt-4">
                    <a href="{{ route('payroll.index', ['month' => $slip->month->format('Y-m')]) }}" class="btn btn-light">Cancel</a>
                    <button type="submit" class="btn btn-warning px-4" :disabled="submitting || net < 0">
                        <span x-show="submitting" x-cloak class="spinner-border spinner-border-sm me-2"></span>
                        Save slip
                    </button>
                </div>
            </form>

            <div class="card border-danger-subtle mt-4">
                <div class="card-body p-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div>
                        <div class="fw-semibold">Remove from this month</div>
                        <p class="small text-secondary mb-0">For someone who left before payday. Their advances stay on record.</p>
                    </div>
                    <form method="POST" action="{{ route('payroll.slips.destroy', $slip) }}" x-data="{ sure: false }">
                        @csrf
                        @method('DELETE')
                        <button type="button" class="btn btn-outline-danger" x-show="! sure" @click="sure = true">Remove slip</button>
                        <span x-show="sure" x-cloak><span class="d-flex flex-wrap align-items-center gap-2">
                            <button type="submit" class="btn btn-danger">Yes, remove</button>
                            <button type="button" class="btn btn-light" @click="sure = false">Keep</button>
                        </span></span>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function slipForm(initial) {
            return {
                ...initial,
                submitting: false,

                num(value) {
                    const parsed = parseFloat(value);

                    return Number.isFinite(parsed) ? parsed : 0;
                },

                get dayRate() {
                    return this.basic / this.days;
                },

                get absence() {
                    return this.num(this.absent) > 0 ? Math.round(this.dayRate * this.num(this.absent)) : 0;
                },

                get net() {
                    return Math.round((this.basic - this.absence + this.num(this.bonus) - this.num(this.advance) - this.num(this.other)) * 100) / 100;
                },

                money(value) {
                    const abs = Math.abs(value);
                    const digits = Number.isInteger(Math.round(abs * 100) / 100) ? 0 : 2;

                    return (value < 0 ? '-' : '') + 'Rs. ' + abs.toLocaleString('en-US', { minimumFractionDigits: digits, maximumFractionDigits: 2 });
                },
            };
        }
    </script>
</x-layouts.app>
