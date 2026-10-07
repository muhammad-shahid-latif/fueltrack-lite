    <form method="POST" action="{{ $action }}" novalidate
          x-data="closingForm({
              totalSales: {{ $summary['total_sales'] }},
              cashExpenses: {{ $summary['cash_expenses'] }},
              creditSales: {{ $summary['credit_sales'] }},
              receipts: {{ $summary['customer_receipts'] }},
              supplierCash: {{ $summary['supplier_payments'] }},
              openingCash: @js(old('opening_cash', $values['opening_cash'])),
              cardSales: @js(old('card_sales', $values['card_sales'])),
              actualCash: @js(old('actual_cash', $values['actual_cash'])),
          })"
          @submit="submitting = true">
        @csrf
        @if ($method === 'PUT')
            @method('PUT')
        @else
            <input type="hidden" name="business_date" value="{{ $date->toDateString() }}">
        @endif

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header">Sales on {{ $date->format('d M Y') }}</div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-7 fw-normal text-secondary">Fuel sold</dt>
                            <dd class="col-5 text-end">@liters($summary['total_liters'])</dd>
                            <dt class="col-7 fw-normal text-secondary">Fuel sales</dt>
                            <dd class="col-5 text-end ft-num">@money($summary['fuel_sales'])</dd>
                            <dt class="col-7 fw-normal text-secondary">Counter items</dt>
                            <dd class="col-5 text-end ft-num">@money($summary['counter_sales'])</dd>
                            <dt class="col-7">Total sales</dt>
                            <dd class="col-5 text-end ft-num fw-bold">@money($summary['total_sales'])</dd>
                            <dt class="col-7 fw-normal text-secondary">Total expenses</dt>
                            <dd class="col-5 text-end">@money($summary['total_expenses'])</dd>
                            <dt class="col-7 fw-normal text-secondary">of which paid in cash</dt>
                            <dd class="col-5 text-end">@money($summary['cash_expenses'])</dd>
                        </dl>

                        <hr>

                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label for="card_sales" class="form-label">Card sales</label>
                                <input type="text" inputmode="decimal" id="card_sales" name="card_sales" x-model="cardSales"
                                       class="form-control @error('card_sales') is-invalid @enderror">
                                @error('card_sales') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-sm-6">
                                <span class="form-label d-block">Credit sales</span>
                                <a href="{{ route('credits.index', ['from' => $date->toDateString(), 'to' => $date->toDateString()]) }}"
                                   class="form-control d-flex justify-content-between align-items-center text-decoration-none bg-body-tertiary" title="From the debt register">
                                    <span class="ft-num fw-semibold text-body">@money($summary['credit_sales'])</span>
                                    <i class="bi bi-journal-bookmark text-secondary"></i>
                                </a>
                                <div class="form-text">From the debt register.</div>
                            </div>
                            <div class="col-12">
                                <div class="d-flex justify-content-between bg-body-tertiary rounded p-2">
                                    <span class="text-secondary">Cash sales</span>
                                    <span class="fw-bold" :class="cashSales < 0 && 'text-danger'" x-text="money(cashSales)"></span>
                                </div>
                                <div class="small text-danger mt-1" x-show="cashSales < 0" x-cloak>Card + credit cannot exceed total sales.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header">Cash reconciliation</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="opening_cash" class="form-label">Opening cash</label>
                            <input type="text" inputmode="decimal" id="opening_cash" name="opening_cash" x-model="openingCash"
                                   class="form-control @error('opening_cash') is-invalid @enderror">
                            @error('opening_cash') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <table class="table table-sm mb-3">
                            <tr><td class="text-secondary">Opening cash</td><td class="text-end" x-text="money(num(openingCash))"></td></tr>
                            <tr><td class="text-secondary">+ Cash sales</td><td class="text-end" x-text="money(cashSales)"></td></tr>
                            <tr x-show="receipts > 0"><td class="text-secondary">+ Received from credit customers</td><td class="text-end" x-text="money(receipts)"></td></tr>
                            <tr><td class="text-secondary">− Cash expenses</td><td class="text-end" x-text="money(-cashExpenses)"></td></tr>
                            <tr x-show="supplierCash > 0"><td class="text-secondary">− Cash paid to suppliers</td><td class="text-end" x-text="money(-supplierCash)"></td></tr>
                            <tr class="fw-bold"><td>Expected cash</td><td class="text-end" x-text="money(expectedCash)"></td></tr>
                        </table>

                        <div class="mb-3">
                            <label for="actual_cash" class="form-label fw-semibold">Actual cash counted</label>
                            <input type="text" inputmode="decimal" id="actual_cash" name="actual_cash" x-model="actualCash"
                                   class="form-control form-control-lg @error('actual_cash') is-invalid @enderror" placeholder="Count the drawer" required>
                            @error('actual_cash') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <template x-if="actualCash !== ''">
                            <div class="alert mb-0 d-flex justify-content-between align-items-center"
                                 :class="difference === 0 ? 'alert-success' : 'alert-danger'">
                                <span>
                                    <i class="bi" :class="difference === 0 ? 'bi-check-circle' : 'bi-exclamation-triangle-fill'"></i>
                                    <span x-text="difference === 0 ? 'Cash matches' : (difference < 0 ? 'Cash short' : 'Cash excess')"></span>
                                </span>
                                <span class="fw-bold fs-5" x-text="(difference > 0 ? '+' : '') + money(difference)"></span>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card">
                    <div class="card-body d-flex flex-wrap gap-3 align-items-end p-4">
                        <div class="flex-grow-1">
                            <label for="notes" class="form-label">Notes <span class="text-secondary small">(optional)</span></label>
                            <input type="text" id="notes" name="notes" value="{{ old('notes', $values['notes']) }}" maxlength="500"
                                   class="form-control @error('notes') is-invalid @enderror" placeholder="e.g. Reason for cash difference">
                            @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="d-flex gap-2">
                            @isset($cancelUrl)
                                <a href="{{ $cancelUrl }}" class="btn btn-light btn-lg">Cancel</a>
                            @endisset
                            <button type="submit" class="btn btn-warning btn-lg px-4"
                                    :disabled="submitting || actualCash === '' || cashSales < 0">
                                <span x-show="submitting" x-cloak class="spinner-border spinner-border-sm me-2"></span>
                                <i class="bi bi-{{ $submitIcon }} me-1"></i> {{ $submitLabel }}
                            </button>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent small text-secondary px-4">{{ $footer }}</div>
                </div>
            </div>
        </div>
    </form>

    <script>
        function closingForm(initial) {
            return {
                ...initial,
                submitting: false,

                num(value) {
                    const parsed = parseFloat(value);

                    return Number.isFinite(parsed) ? parsed : 0;
                },

                round(value) {
                    return Math.round(value * 100) / 100;
                },

                get cashSales() {
                    return this.round(this.totalSales - this.num(this.cardSales) - this.creditSales);
                },

                get expectedCash() {
                    return this.round(this.num(this.openingCash) + this.cashSales + this.receipts - this.cashExpenses - this.supplierCash);
                },

                get difference() {
                    return this.round(this.num(this.actualCash) - this.expectedCash);
                },

                money(value) {
                    const sign = value < 0 ? '-' : '';

                    const abs = Math.abs(value);
                    const digits = Number.isInteger(abs) ? 0 : 2;

                    return sign + 'Rs. ' + abs.toLocaleString('en-US', { minimumFractionDigits: digits, maximumFractionDigits: 2 });
                },
            };
        }
    </script>
