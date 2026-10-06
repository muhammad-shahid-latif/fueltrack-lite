<x-layouts.app title="Record purchase" subtitle="Stock goes up as soon as this is saved">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-6">
            <div class="card">
                <div class="card-body p-4 p-md-5">
                    <form method="POST" action="{{ route('purchases.store') }}" novalidate
                          x-data="{ units: @js($products->mapWithKeys(fn ($p) => [$p->id => $p->unit->value])), productId: @js(old('fuel_product_id') ? (int) old('fuel_product_id') : null),
                                    quantity: @js(old('quantity', '')), rate: @js(old('rate', '')), submitting: false,
                                    get unit() { return this.units[this.productId] ?? '' },
                                    get total() { const t = parseFloat(this.quantity) * parseFloat(this.rate); return Number.isFinite(t) ? Math.round(t * 100) / 100 : 0; } }"
                          @submit="submitting = true">
                        @csrf

                        <div class="mb-4">@include('partials.business-date', ['date' => $businessDate])</div>

                        <div class="row g-3">
                            <div class="col-12">
                                <label for="fuel_product_id" class="form-label">Product</label>
                                <select id="fuel_product_id" name="fuel_product_id" x-model.number="productId"
                                        class="form-select form-select-lg @error('fuel_product_id') is-invalid @enderror" required>
                                    <option value="">Choose a product</option>
                                    <optgroup label="Fuel">
                                        @foreach ($products->filter->isMetered() as $product)
                                            <option value="{{ $product->id }}" @selected(old('fuel_product_id') == $product->id)>{{ $product->name }}</option>
                                        @endforeach
                                    </optgroup>
                                    <optgroup label="Counter items">
                                        @foreach ($products->reject->isMetered() as $product)
                                            <option value="{{ $product->id }}" @selected(old('fuel_product_id') == $product->id)>{{ $product->name }}</option>
                                        @endforeach
                                    </optgroup>
                                </select>
                                @error('fuel_product_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-sm-6">
                                <label for="quantity" class="form-label">Quantity <span class="text-secondary fw-normal" x-text="unit ? '(' + unit + ')' : ''"></span></label>
                                <input type="text" inputmode="decimal" id="quantity" name="quantity" x-model="quantity" placeholder="10000"
                                       class="form-control form-control-lg ft-num @error('quantity') is-invalid @enderror" required>
                                @error('quantity') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-sm-6">
                                <label for="rate" class="form-label">Purchase rate <span class="text-secondary fw-normal" x-text="unit ? '(Rs. per ' + unit + ')' : ''"></span></label>
                                <input type="text" inputmode="decimal" id="rate" name="rate" x-model="rate" placeholder="270"
                                       class="form-control form-control-lg ft-num @error('rate') is-invalid @enderror" required>
                                @error('rate') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12">
                                <div class="calc-panel d-flex justify-content-between align-items-center">
                                    <span class="calc-label fs-6">Total purchase value</span>
                                    <span class="calc-value is-total" x-text="total > 0 ? 'Rs. ' + total.toLocaleString('en-US', { minimumFractionDigits: Number.isInteger(total) ? 0 : 2, maximumFractionDigits: 2 }) : '—'"></span>
                                </div>
                            </div>

                            <div class="col-sm-7">
                                <label for="supplier_name" class="form-label">Supplier <span class="text-secondary fw-normal small">(optional)</span></label>
                                <input type="text" id="supplier_name" name="supplier_name" value="{{ old('supplier_name') }}" maxlength="100"
                                       class="form-control @error('supplier_name') is-invalid @enderror">
                                @error('supplier_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-sm-5">
                                <label for="invoice_number" class="form-label">Invoice no. <span class="text-secondary fw-normal small">(optional)</span></label>
                                <input type="text" id="invoice_number" name="invoice_number" value="{{ old('invoice_number') }}" maxlength="50"
                                       class="form-control @error('invoice_number') is-invalid @enderror">
                                @error('invoice_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12">
                                <label for="notes" class="form-label">Notes <span class="text-secondary fw-normal small">(optional)</span></label>
                                <input type="text" id="notes" name="notes" value="{{ old('notes') }}" maxlength="500"
                                       class="form-control @error('notes') is-invalid @enderror">
                                @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('purchases.index') }}" class="btn btn-light">Cancel</a>
                            <button type="submit" class="btn btn-warning px-4" :disabled="submitting">
                                <span x-show="submitting" x-cloak class="spinner-border spinner-border-sm me-2"></span>
                                Save purchase
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
