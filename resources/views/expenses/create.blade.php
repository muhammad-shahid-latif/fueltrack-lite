<x-layouts.app title="Add expense" subtitle="Only cash payments reduce the cash drawer at closing">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-6">
            <div class="card">
                <div class="card-body p-4 p-md-5">
                    <form method="POST" action="{{ route('expenses.store') }}" novalidate x-data="{ submitting: false }" @submit="submitting = true">
                        @csrf

                        <div class="mb-4">@include('partials.business-date', ['date' => $businessDate])</div>

                        <div class="row g-3">
                            <div class="col-12">
                                <label for="category" class="form-label">Category</label>
                                <select id="category" name="category" class="form-select form-select-lg @error('category') is-invalid @enderror" required>
                                    <option value="">Select category…</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->value }}" @selected(old('category') === $category->value)>{{ $category->label() }}</option>
                                    @endforeach
                                </select>
                                @error('category') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12">
                                <label for="description" class="form-label">Description</label>
                                <input type="text" id="description" name="description" value="{{ old('description') }}" maxlength="255"
                                       class="form-control form-control-lg @error('description') is-invalid @enderror" placeholder="e.g. Generator fuel" required>
                                @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-sm-6">
                                <label for="amount" class="form-label">Amount (Rs.)</label>
                                <input type="text" inputmode="decimal" id="amount" name="amount" value="{{ old('amount') }}"
                                       class="form-control form-control-lg @error('amount') is-invalid @enderror" placeholder="5000" required>
                                @error('amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-sm-6">
                                <label class="form-label d-block">Payment method</label>
                                <div class="btn-group w-100" role="group" aria-label="Payment method">
                                    @foreach ($paymentMethods as $method)
                                        <input type="radio" class="btn-check" name="payment_method" id="pm_{{ $method->value }}" value="{{ $method->value }}"
                                               @checked(old('payment_method', 'cash') === $method->value)>
                                        <label class="btn btn-outline-dark py-2" for="pm_{{ $method->value }}">{{ $method->label() }}</label>
                                    @endforeach
                                </div>
                                                                @error('payment_method') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12">
                                <label for="notes" class="form-label">Notes <span class="text-secondary small">(optional)</span></label>
                                <input type="text" id="notes" name="notes" value="{{ old('notes') }}" maxlength="500"
                                       class="form-control @error('notes') is-invalid @enderror">
                                @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('expenses.index') }}" class="btn btn-light">Cancel</a>
                            <button type="submit" class="btn btn-warning px-4" :disabled="submitting">
                                <span x-show="submitting" x-cloak class="spinner-border spinner-border-sm me-2"></span>
                                Save expense
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
