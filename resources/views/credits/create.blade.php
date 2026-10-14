<x-layouts.app title="Credit sale" subtitle="Fuel given to an account customer; it is collected later">
    <div class="row justify-content-center">
        <div class="col-lg-9 col-xl-7">
            <div class="card">
                <div class="card-body p-4 p-md-5">
                    @if ($customers->isEmpty())
                        <div class="empty-state"><i class="bi bi-person-vcard"></i>No credit customers yet.
                            @if (auth()->user()->isOwner())<div class="mt-3"><a href="{{ route('customers.create') }}" class="btn btn-warning">Add a customer</a></div>@endif
                        </div>
                    @else
                        <form method="POST" action="{{ route('credits.store') }}" novalidate
                              x-data="creditForm(@js($customers), @js($fuels->mapWithKeys(fn ($f) => [$f->id => (float) $f->selling_price])), @js(old('customer_id', $selectedCustomerId)), @js(old('fuel_product_id', (string) ($fuels->first()?->id ?? ''))), @js(old('quantity', '')), @js(old('amount', '')))"
                              @submit="submitting = true">
                            @csrf
                            <div class="mb-4">@include('partials.business-date', ['date' => $businessDate])</div>

                            <div class="row g-3">
                                <div class="col-12">
                                    <label for="customer_id" class="form-label">Customer</label>
                                    <select id="customer_id" name="customer_id" x-model.number="customerId" class="form-select form-select-lg @error('customer_id') is-invalid @enderror">
                                        <option value="">Choose a customer…</option>
                                        <template x-for="c in customers" :key="c.id">
                                            <option :value="c.id" x-text="c.name" :selected="c.id === customerId"></option>
                                        </template>
                                    </select>
                                    @error('customer_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    <div class="small mt-2" x-show="customer" x-cloak>
                                        Owes <strong x-text="money(customer?.balance ?? 0)"></strong>
                                        <template x-if="customer?.limit !== null"><span> · limit <span x-text="money(customer?.limit ?? 0)"></span> · room <strong :class="room < total ? 'text-danger' : 'text-success'" x-text="money(room)"></strong></span></template>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <label class="form-label d-block">What was given</label>
                                    <div class="d-flex flex-wrap gap-2">
                                        @foreach ($fuels as $fuel)
                                            <input type="radio" class="btn-check" name="fuel_product_id" id="f_{{ $fuel->id }}" value="{{ $fuel->id }}" x-model="fuel">
                                            <label class="btn btn-outline-dark" for="f_{{ $fuel->id }}">{{ $fuel->name }} <small class="opacity-75">Rs. {{ \App\Support\Format::number($fuel->selling_price) }}</small></label>
                                        @endforeach
                                        <input type="radio" class="btn-check" name="fuel_product_id" id="f_amount" value="" x-model="fuel">
                                        <label class="btn btn-outline-dark" for="f_amount">Other amount</label>
                                    </div>
                                </div>

                                <div class="col-sm-6" x-show="fuel !== ''">
                                    <label for="quantity" class="form-label">Liters</label>
                                    <input type="text" inputmode="decimal" id="quantity" name="quantity" x-model="qty" :disabled="fuel === ''" class="form-control form-control-lg ft-num @error('quantity') is-invalid @enderror">
                                    @error('quantity') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-sm-6" x-show="fuel === ''" x-cloak>
                                    <label for="amount" class="form-label">Amount (Rs.)</label>
                                    <input type="text" inputmode="decimal" id="amount" name="amount" x-model="amount" :disabled="fuel !== ''" class="form-control form-control-lg ft-num">
                                </div>
                                <div class="col-sm-6">
                                    <label for="vehicle_number" class="form-label">Vehicle no.</label>
                                    <input type="text" id="vehicle_number" name="vehicle_number" :value="customer?.vehicle ?? ''" maxlength="30" class="form-control form-control-lg text-uppercase">
                                </div>
                                @error('amount') <div class="col-12"><div class="alert alert-danger mb-0">{{ $message }}</div></div> @enderror

                                <div class="col-12">@include('credits.partials.owed-split')</div>

                                <div class="col-12">
                                    <div class="calc-panel d-flex justify-content-between align-items-center">
                                        <span class="calc-label fs-6">Added to the account</span>
                                        <span class="calc-value is-total" x-text="total > 0 ? money(total) : '—'"></span>
                                    </div>
                                </div>
                                <div class="col-sm-5">
                                    <input type="text" name="slip_number" value="{{ old('slip_number') }}" maxlength="30" class="form-control" placeholder="Slip / token no.">
                                </div>
                                <div class="col-sm-7">
                                    <input type="text" name="notes" value="{{ old('notes') }}" maxlength="500" class="form-control" placeholder="Notes (optional)">
                                </div>
                            </div>

                            <div class="d-flex gap-2 justify-content-end mt-4">
                                <a href="{{ route('credits.index') }}" class="btn btn-light">Cancel</a>
                                <button type="submit" class="btn btn-warning px-4" :disabled="submitting || ! customerId || total <= 0">
                                    <span x-show="submitting" x-cloak class="spinner-border spinner-border-sm me-2"></span>Save credit sale
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script>
        function creditForm(customers, rates, customerId, fuel, qty, amount) {
            return {
                customers, rates, fuel, qty, amount, submitting: false,
                customerId: customerId ? Number(customerId) : null,

                get customer() { return this.customers.find((c) => c.id === this.customerId) ?? null; },
                get total() {
                    const t = this.fuel === '' ? parseFloat(this.amount) : parseFloat(this.qty) * (this.rates[this.fuel] ?? 0);
                    return Number.isFinite(t) ? Math.round(t * 100) / 100 : 0;
                },
                get room() { return this.customer && this.customer.limit !== null ? Math.max(0, this.customer.limit - this.customer.balance) : Infinity; },
                money(v) { return 'Rs. ' + Number(v).toLocaleString('en-US', { maximumFractionDigits: 2 }); },
            };
        }
    </script>
</x-layouts.app>
