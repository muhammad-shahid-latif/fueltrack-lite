<x-layouts.app title="Receive delivery" subtitle="Stock, tank level and the weighted average cost update as soon as this is saved">
    <form method="POST" action="{{ route('purchases.store') }}" novalidate
          x-data="purchaseForm({
              products: @js($productData),
              tanks: @js($tanks),
              suppliers: @js($suppliers->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'terms' => $s->payment_terms_days])),
              productId: @js(old('fuel_product_id') ? (int) old('fuel_product_id') : ($selectedTankId ? $tanks->firstWhere('id', $selectedTankId)['product'] ?? null : null)),
              tankId: @js(old('tank_id') ? (int) old('tank_id') : $selectedTankId),
              supplierId: @js(old('supplier_id') ? (int) old('supplier_id') : null),
              quantity: @js(old('quantity', '')),
              rate: @js(old('rate', '')),
              paidNow: @js(old('paid_now', '')),
              dueDate: @js(old('due_date', '')),
              today: @js($businessDate->toDateString()),
          })"
          @submit="submitting = true">
        @csrf

        <div class="row g-4">
            <div class="col-xl-7">
                <div class="card h-100">
                    <div class="card-header">What arrived</div>
                    <div class="card-body p-4 p-md-5">
                        <div class="mb-4">@include('partials.business-date', ['date' => $businessDate])</div>

                        <div class="row g-3">
                            <div class="col-12">
                                <label for="fuel_product_id" class="form-label">Product</label>
                                <select id="fuel_product_id" name="fuel_product_id" x-model.number="productId" @change="tankId = firstTank()"
                                        class="form-select form-select-lg @error('fuel_product_id') is-invalid @enderror" required>
                                    <option value="">Choose a product</option>
                                    <optgroup label="Fuel">
                                        @foreach ($products->filter->isMetered() as $product)
                                            <option value="{{ $product->id }}">{{ $product->name }}</option>
                                        @endforeach
                                    </optgroup>
                                    <optgroup label="Counter items">
                                        @foreach ($products->reject->isMetered() as $product)
                                            <option value="{{ $product->id }}">{{ $product->name }}</option>
                                        @endforeach
                                    </optgroup>
                                </select>
                                @error('fuel_product_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12" x-show="productTanks.length" x-cloak>
                                <span class="form-label d-block">Into tank</span>
                                <div class="row g-2">
                                    <template x-for="tank in productTanks" :key="tank.id">
                                        <div class="col-sm-6">
                                            <input type="radio" class="btn-check" name="tank_id" :id="'tank_' + tank.id" :value="tank.id" x-model.number="tankId">
                                            <label class="btn btn-outline-dark w-100 text-start d-flex align-items-center gap-3 py-2" :for="'tank_' + tank.id">
                                                <span class="flex-grow-1">
                                                    <span class="d-block fw-bold" x-text="tank.name"></span>
                                                    <span class="small opacity-75" x-text="fmt(tank.level) + ' L in · room ' + fmt(tank.ullage) + ' L'"></span>
                                                </span>
                                                <span class="share-bar" style="width: 60px;"><span :style="'width:' + tank.percent + '%'"></span></span>
                                            </label>
                                        </div>
                                    </template>
                                </div>
                                @error('tank_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-sm-6">
                                <label for="quantity" class="form-label">Quantity <span class="text-secondary fw-normal" x-text="unit ? '(' + unit + ')' : ''"></span></label>
                                <input type="text" inputmode="decimal" id="quantity" name="quantity" x-model="quantity" placeholder="10000"
                                       class="form-control form-control-lg ft-num @error('quantity') is-invalid @enderror"
                                       :class="{ 'is-invalid': overflow }" required>
                                @error('quantity')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @else
                                    <div class="invalid-feedback" x-text="overflow ? 'More than the room left in this tank.' : ''"></div>
                                @enderror
                            </div>

                            <div class="col-sm-6">
                                <label for="rate" class="form-label">Purchase rate <span class="text-secondary fw-normal" x-text="unit ? '(Rs. per ' + unit + ')' : ''"></span></label>
                                <input type="text" inputmode="decimal" id="rate" name="rate" x-model="rate" placeholder="270"
                                       class="form-control form-control-lg ft-num @error('rate') is-invalid @enderror" required>
                                @error('rate') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-sm-6">
                                <label for="invoice_number" class="form-label">Invoice no. <span class="text-secondary fw-normal small">(optional)</span></label>
                                <input type="text" id="invoice_number" name="invoice_number" value="{{ old('invoice_number') }}" maxlength="50"
                                       class="form-control @error('invoice_number') is-invalid @enderror">
                            </div>
                            <div class="col-sm-6">
                                <label for="notes" class="form-label">Notes <span class="text-secondary fw-normal small">(optional)</span></label>
                                <input type="text" id="notes" name="notes" value="{{ old('notes') }}" maxlength="500" class="form-control" placeholder="Tanker no., driver…">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-5">
                <div class="calc-panel mb-4 p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="calc-label fs-6">Bill amount</span>
                        <span class="calc-value is-total fs-3" x-text="total > 0 ? money(total) : '—'"></span>
                    </div>
                    <div class="row g-2 text-center">
                        <div class="col-4">
                            <div class="calc-label">Stock now</div>
                            <div class="calc-value" style="font-size: 1.05rem;" x-text="product ? fmt(product.stock) : '—'"></div>
                        </div>
                        <div class="col-4">
                            <div class="calc-label">Avg cost now</div>
                            <div class="calc-value" style="font-size: 1.05rem;" x-text="product && product.average !== null ? fmt(product.average) : '—'"></div>
                        </div>
                        <div class="col-4">
                            <div class="calc-label">New avg cost</div>
                            <div class="calc-value is-total" style="font-size: 1.05rem;" x-text="newAverage !== null ? fmt(newAverage) : '—'"></div>
                        </div>
                    </div>
                    <div class="small mt-3 opacity-75">Weighted average = (stock × current average + quantity × rate) ÷ (stock + quantity)</div>
                </div>

                <div class="card">
                    <div class="card-header">Supplier &amp; payment</div>
                    <div class="card-body p-4">
                        <label for="supplier_id" class="form-label">Supplier</label>
                        <select id="supplier_id" name="supplier_id" x-model.number="supplierId" @change="setDue()" class="form-select @error('supplier_id') is-invalid @enderror">
                            <option value="">Not in the register (paid on the spot)</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}">{{ $supplier->name }} · {{ $supplier->termsLabel() }}</option>
                            @endforeach
                        </select>
                        @error('supplier_id') <div class="invalid-feedback">{{ $message }}</div> @enderror

                        <div x-show="! supplierId" class="mt-3">
                            <input type="text" name="supplier_name" value="{{ old('supplier_name') }}" maxlength="100" class="form-control" placeholder="Supplier name (optional)" :disabled="supplierId">
                        </div>

                        <div x-show="supplierId" x-cloak class="mt-3">
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <label for="paid_now" class="form-label">Paid now (Rs.)</label>
                                    <div class="input-group">
                                        <input type="text" inputmode="decimal" id="paid_now" name="paid_now" x-model="paidNow" class="form-control ft-num @error('paid_now') is-invalid @enderror" placeholder="0" :disabled="! supplierId">
                                        <button type="button" class="btn btn-light" @click="paidNow = total">Full</button>
                                    </div>
                                    @error('paid_now') <div class="text-danger small">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-sm-6">
                                    <label for="due_date" class="form-label">Balance due on</label>
                                    <input type="date" id="due_date" name="due_date" x-model="dueDate" :min="today" class="form-control @error('due_date') is-invalid @enderror" :disabled="! supplierId">
                                    @error('due_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-12">
                                    <div class="d-flex flex-wrap gap-2">
                                        @foreach ($paymentMethods as $method)
                                            <input type="radio" class="btn-check" name="payment_method" id="ppm_{{ $method->value }}" value="{{ $method->value }}" @checked(old('payment_method', 'bank') === $method->value) :disabled="! supplierId">
                                            <label class="btn btn-outline-dark btn-sm" for="ppm_{{ $method->value }}">{{ $method->label() }}</label>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="d-flex justify-content-between small fw-semibold">
                                        <span>Added to payables</span>
                                        <span class="ft-num amount-out" x-text="money(Math.max(0, total - (parseFloat(paidNow) || 0)))"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2 justify-content-end mt-4">
                    <a href="{{ route('purchases.index') }}" class="btn btn-light">Cancel</a>
                    <button type="submit" class="btn btn-warning btn-lg px-4" :disabled="submitting || overflow || total <= 0">
                        <span x-show="submitting" x-cloak class="spinner-border spinner-border-sm me-2"></span>
                        <i class="bi bi-truck me-1"></i> Save delivery
                    </button>
                </div>
            </div>
        </div>
    </form>

    <script>
        function purchaseForm(initial) {
            return {
                ...initial,
                submitting: false,

                init() {
                    if (this.productId && ! this.tankId) this.tankId = this.firstTank();
                },

                get product() { return this.products[this.productId] ?? null; },
                get unit() { return this.product?.unit ?? ''; },
                get productTanks() { return this.tanks.filter((t) => t.product === this.productId); },
                get tank() { return this.tanks.find((t) => t.id === this.tankId) ?? null; },
                get qty() { const q = parseFloat(this.quantity); return Number.isFinite(q) ? q : 0; },
                get total() { const t = this.qty * parseFloat(this.rate); return Number.isFinite(t) ? Math.round(t * 100) / 100 : 0; },
                get overflow() { return this.tank !== null && this.qty > this.tank.ullage + 0.005; },
                get newAverage() {
                    const r = parseFloat(this.rate);
                    if (! this.product || ! this.qty || ! Number.isFinite(r)) return null;
                    const onHand = Math.max(0, this.product.stock);
                    const current = this.product.average ?? r;
                    return Math.round(((onHand * current + this.qty * r) / (onHand + this.qty)) * 100) / 100;
                },

                firstTank() {
                    const tanks = this.productTanks;
                    if (! tanks.length) return null;
                    return [...tanks].sort((a, b) => b.ullage - a.ullage)[0].id;
                },

                setDue() {
                    const supplier = this.suppliers.find((s) => s.id === this.supplierId);
                    if (! supplier) return;
                    const due = new Date(this.today + 'T00:00:00');
                    due.setDate(due.getDate() + supplier.terms);
                    this.dueDate = due.toISOString().slice(0, 10);
                },

                fmt(v) { return Number(v).toLocaleString('en-US', { maximumFractionDigits: 2 }); },
                money(v) { return 'Rs. ' + this.fmt(v); },
            };
        }
    </script>
</x-layouts.app>
