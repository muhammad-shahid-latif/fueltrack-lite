@php($editing = $expense->exists)
<x-layouts.app :title="$editing ? 'Edit expense' : 'Add expense'" subtitle="Only cash payments reduce the cash drawer at closing">
    <div class="row justify-content-center">
        <div class="col-lg-9 col-xl-7">
            <div class="card">
                <div class="card-body p-4 p-md-5">
                    <form method="POST" action="{{ $editing ? route('expenses.update', $expense) : route('expenses.store') }}" novalidate
                          x-data="{ submitting: false, category: @js(old('category', $expense->category?->value ?? '')) }" @submit="submitting = true">
                        @csrf
                        @if ($editing) @method('PUT') @endif

                        <div class="mb-4">@include('partials.business-date', ['date' => $businessDate])</div>

                        <div class="mb-4">
                            <span class="form-label d-block">Category</span>
                            @foreach ($groups as $group => $categories)
                                <div class="small text-secondary fw-semibold text-uppercase mt-2 mb-1" style="letter-spacing: .06em;">{{ $group }}</div>
                                <div class="d-flex flex-wrap gap-2">
                                    @foreach ($categories as $category)
                                        <input type="radio" class="btn-check" name="category" id="cat_{{ $category->value }}" value="{{ $category->value }}" x-model="category">
                                        <label class="btn btn-outline-dark btn-sm" for="cat_{{ $category->value }}"><i class="bi bi-{{ $category->icon() }} me-1"></i>{{ $category->label() }}</label>
                                    @endforeach
                                </div>
                            @endforeach
                            @error('category') <div class="text-danger small mt-2">{{ $message }}</div> @enderror
                        </div>

                        <div class="row g-3">
                            <div class="col-12">
                                <label for="description" class="form-label">Description</label>
                                <input type="text" id="description" name="description" value="{{ old('description', $expense->description) }}" maxlength="255"
                                       class="form-control form-control-lg @error('description') is-invalid @enderror" placeholder="e.g. WAPDA bill for September" required>
                                @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-sm-6">
                                <label for="amount" class="form-label">Amount (Rs.)</label>
                                <input type="text" inputmode="decimal" id="amount" name="amount" value="{{ old('amount', $editing ? \App\Support\Format::meter($expense->amount) : '') }}"
                                       class="form-control form-control-lg ft-num @error('amount') is-invalid @enderror" placeholder="5000" required>
                                @error('amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-sm-6">
                                <span class="form-label d-block">Paid by</span>
                                <div class="d-flex flex-wrap gap-2">
                                    @foreach ($paymentMethods as $method)
                                        <input type="radio" class="btn-check" name="payment_method" id="pm_{{ $method->value }}" value="{{ $method->value }}"
                                               @checked(old('payment_method', $expense->payment_method?->value ?? 'cash') === $method->value)>
                                        <label class="btn btn-outline-dark py-2" for="pm_{{ $method->value }}">{{ $method->label() }}</label>
                                    @endforeach
                                </div>
                                @error('payment_method') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12">
                                <label for="notes" class="form-label">Notes <span class="text-secondary small">(optional)</span></label>
                                <input type="text" id="notes" name="notes" value="{{ old('notes', $expense->notes) }}" maxlength="500"
                                       class="form-control @error('notes') is-invalid @enderror" placeholder="Bill no., vendor, reference…">
                                @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('expenses.index') }}" class="btn btn-light">Cancel</a>
                            <button type="submit" class="btn btn-warning px-4" :disabled="submitting || ! category">
                                <span x-show="submitting" x-cloak class="spinner-border spinner-border-sm me-2"></span>
                                {{ $editing ? 'Save changes' : 'Save expense' }}
                            </button>
                        </div>
                    </form>

                    @if ($editing)
                        <form method="POST" action="{{ route('expenses.destroy', $expense) }}" class="mt-4 pt-4 border-top text-end" x-data="{ sure: false }">
                            @csrf
                            @method('DELETE')
                            <button type="button" class="btn btn-outline-danger" x-show="! sure" @click="sure = true"><i class="bi bi-trash3 me-1"></i> Delete expense</button>
                            <span x-show="sure" x-cloak><span class="d-inline-flex gap-2"><button type="submit" class="btn btn-danger">Yes, delete</button><button type="button" class="btn btn-light" @click="sure = false">Keep</button></span></span>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
