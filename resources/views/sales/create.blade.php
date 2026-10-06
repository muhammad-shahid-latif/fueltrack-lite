<x-layouts.app title="Counter sale" subtitle="Engine oil and other items sold at the counter">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-6">
            <div class="card">
                <div class="card-body p-4 p-md-5">
                    @if ($items->isEmpty())
                        <div class="empty-state">
                            <i class="bi bi-basket"></i>
                            No counter items yet.
                            @if (auth()->user()->isOwner())
                                <div class="mt-3"><a href="{{ route('fuel-products.create') }}" class="btn btn-warning">Add a product</a></div>
                            @endif
                        </div>
                    @else
                        <form method="POST" action="{{ route('sales.store') }}" novalidate
                              x-data="{ items: @js($items), itemId: @js(old('fuel_product_id') ? (int) old('fuel_product_id') : null), quantity: @js(old('quantity', '1')), submitting: false,
                                        get item() { return this.items.find((i) => i.id === this.itemId) ?? null },
                                        get total() { const t = parseFloat(this.quantity) * (this.item?.rate ?? 0); return Number.isFinite(t) ? Math.round(t * 100) / 100 : 0 },
                                        money(v) { return 'Rs. ' + v.toLocaleString('en-US', { minimumFractionDigits: Number.isInteger(v) ? 0 : 2, maximumFractionDigits: 2 }) } }"
                              @submit="submitting = true">
                            @csrf

                            <div class="mb-4">@include('partials.business-date', ['date' => $businessDate])</div>

                            <div class="mb-3">
                                <label for="fuel_product_id" class="form-label">Item</label>
                                <select id="fuel_product_id" name="fuel_product_id" x-model.number="itemId"
                                        class="form-select form-select-lg @error('fuel_product_id') is-invalid @enderror" required>
                                    <option value="">Choose an item</option>
                                    <template x-for="option in items" :key="option.id">
                                        <option :value="option.id" :selected="option.id === itemId" x-text="`${option.name} — ${option.stock} ${option.unit} in stock`"></option>
                                    </template>
                                </select>
                                @error('fuel_product_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="mb-4">
                                <label for="quantity" class="form-label">Quantity <span class="text-secondary fw-normal" x-text="item ? '(' + item.unit + ')' : ''"></span></label>
                                <input type="text" inputmode="decimal" id="quantity" name="quantity" x-model="quantity"
                                       class="form-control form-control-lg ft-num @error('quantity') is-invalid @enderror"
                                       :class="{ 'is-invalid': item && parseFloat(quantity) > item.stock }" required>
                                @error('quantity')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @else
                                    <div class="invalid-feedback" x-text="item ? 'Only ' + item.stock + ' ' + item.unit + ' in stock.' : ''"></div>
                                @enderror
                            </div>

                            <div class="calc-panel mb-4">
                                <div class="row text-center g-2">
                                    <div class="col-6">
                                        <div class="calc-label">Rate</div>
                                        <div class="calc-value" x-text="item ? money(item.rate) : '—'"></div>
                                    </div>
                                    <div class="col-6">
                                        <div class="calc-label">Total</div>
                                        <div class="calc-value is-total" x-text="total > 0 ? money(total) : '—'"></div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="notes" class="form-label">Notes <span class="text-secondary fw-normal small">(optional)</span></label>
                                <input type="text" id="notes" name="notes" value="{{ old('notes') }}" maxlength="500" class="form-control" placeholder="e.g. Vehicle number">
                            </div>

                            <div class="d-flex gap-2 justify-content-end">
                                <a href="{{ route('sales.index') }}" class="btn btn-light">Cancel</a>
                                <button type="submit" class="btn btn-warning px-4" :disabled="submitting || ! item || total <= 0">
                                    <span x-show="submitting" x-cloak class="spinner-border spinner-border-sm me-2"></span>
                                    Save sale
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
