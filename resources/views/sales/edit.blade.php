<x-layouts.app title="Edit counter sale" subtitle="Stock adjusts to the corrected quantity">
    @php($product = $sale->fuelProduct)

    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-6">
            <div class="card mb-4">
                <div class="card-body p-4 p-md-5">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
                        <div class="d-flex align-items-center gap-3 {{ \App\Support\Grade::cssClass($product) }}">
                            <span class="grade-dot"></span>
                            <div>
                                <div class="fw-bold fs-5">{{ $product->name }}</div>
                                <div class="small text-secondary">Counter sale · {{ $sale->business_date->format('D, d M Y') }}</div>
                            </div>
                        </div>
                        <span class="small text-secondary text-sm-end">
                            Recorded by {{ $sale->recorder?->name ?? '—' }}<br>{{ $sale->created_at->format('d M Y, h:i A') }}
                        </span>
                    </div>

                    <form method="POST" action="{{ route('sales.update', $sale) }}" novalidate
                          x-data="{ quantity: @js(old('quantity', \App\Support\Format::input($sale->quantity))), rate: @js(old('rate', (float) $sale->rate)),
                                    available: {{ $available }}, submitting: false,
                                    get total() { const t = parseFloat(this.quantity) * parseFloat(this.rate); return Number.isFinite(t) ? Math.round(t * 100) / 100 : 0 },
                                    get overStock() { return parseFloat(this.quantity) > this.available + 0.005 },
                                    money(v) { return 'Rs. ' + v.toLocaleString('en-US', { minimumFractionDigits: Number.isInteger(v) ? 0 : 2, maximumFractionDigits: 2 }) } }"
                          @submit="submitting = true">
                        @csrf
                        @method('PUT')

                        <div class="row g-3 mb-4">
                            <div class="col-sm-6">
                                <label for="quantity" class="form-label">Quantity <span class="text-secondary fw-normal">({{ $product->unit->value }})</span></label>
                                <input type="text" inputmode="decimal" id="quantity" name="quantity" x-model="quantity"
                                       class="form-control form-control-lg ft-num @error('quantity') is-invalid @enderror"
                                       :class="{ 'is-invalid': overStock }" required>
                                @error('quantity')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @else
                                    <div class="invalid-feedback">Only @qty($available, $product->unit) available.</div>
                                    <div class="form-text">Up to @qty($available, $product->unit) available.</div>
                                @enderror
                            </div>
                            <div class="col-sm-6">
                                <label for="rate" class="form-label">Rate</label>
                                <div class="input-group input-group-lg has-validation">
                                    <span class="input-group-text">Rs.</span>
                                    <input type="text" inputmode="decimal" id="rate" name="rate" x-model="rate"
                                           class="form-control ft-num @error('rate') is-invalid @enderror" required>
                                    @error('rate') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                        </div>

                        <div class="calc-panel mb-4">
                            <div class="row text-center g-2">
                                <div class="col-6">
                                    <div class="calc-label">Was</div>
                                    <div class="calc-value">@money($sale->amount)</div>
                                </div>
                                <div class="col-6">
                                    <div class="calc-label">New total</div>
                                    <div class="calc-value is-total" x-text="total > 0 ? money(total) : '—'"></div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="notes" class="form-label">Notes <span class="text-secondary fw-normal small">(optional)</span></label>
                            <input type="text" id="notes" name="notes" value="{{ old('notes', $sale->notes) }}" maxlength="500"
                                   class="form-control @error('notes') is-invalid @enderror" placeholder="e.g. Reason for the correction">
                            @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="d-flex gap-2 justify-content-end">
                            <a href="{{ route('sales.index', ['source' => 'counter']) }}" class="btn btn-light">Cancel</a>
                            <button type="submit" class="btn btn-warning px-4" :disabled="submitting || total <= 0 || overStock">
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
                        <div class="fw-semibold">Delete this sale</div>
                        <p class="small text-secondary mb-0">Returns @qty($sale->quantity, $product->unit) to {{ $product->name }} stock.</p>
                    </div>
                    <form method="POST" action="{{ route('sales.destroy', $sale) }}" x-data="{ sure: false }">
                        @csrf
                        @method('DELETE')
                        <button type="button" class="btn btn-outline-danger" x-show="! sure" @click="sure = true">
                            <i class="bi bi-trash3 me-1"></i> Delete sale
                        </button>
                        <span x-show="sure" x-cloak><span class="d-flex flex-wrap align-items-center gap-2">
                            <button type="submit" class="btn btn-danger">Yes, delete</button>
                            <button type="button" class="btn btn-light" @click="sure = false">Keep it</button>
                        </span></span>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
