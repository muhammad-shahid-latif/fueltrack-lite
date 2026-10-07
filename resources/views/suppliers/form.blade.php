@php($editing = $supplier->exists)
@php($balanceLocked = $editing && ($supplier->purchases()->exists() || $supplier->payments()->exists()))
<x-layouts.app :title="$editing ? 'Edit '.$supplier->name : 'Add supplier'" subtitle="Supplier register">
    <div class="row justify-content-center">
        <div class="col-lg-9 col-xl-7">
            <div class="card">
                <div class="card-body p-4 p-md-5">
                    <form method="POST" action="{{ $editing ? route('suppliers.update', $supplier) : route('suppliers.store') }}" novalidate x-data="{ submitting: false }" @submit="submitting = true">
                        @csrf
                        @if ($editing) @method('PUT') @endif

                        <div class="row g-3">
                            <div class="col-12">
                                <label for="name" class="form-label">Supplier name</label>
                                <input type="text" id="name" name="name" value="{{ old('name', $supplier->name) }}" maxlength="100" class="form-control form-control-lg @error('name') is-invalid @enderror" required>
                                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-sm-6">
                                <label for="contact_person" class="form-label">Contact person</label>
                                <input type="text" id="contact_person" name="contact_person" value="{{ old('contact_person', $supplier->contact_person) }}" maxlength="100" class="form-control @error('contact_person') is-invalid @enderror">
                                @error('contact_person') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-sm-6">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="text" id="phone" name="phone" value="{{ old('phone', $supplier->phone) }}" maxlength="30" class="form-control @error('phone') is-invalid @enderror">
                                @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-12">
                                <label for="address" class="form-label">Address</label>
                                <input type="text" id="address" name="address" value="{{ old('address', $supplier->address) }}" maxlength="255" class="form-control @error('address') is-invalid @enderror">
                                @error('address') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-sm-6">
                                <label for="payment_terms_days" class="form-label">Credit days</label>
                                <input type="number" min="0" max="365" id="payment_terms_days" name="payment_terms_days" value="{{ old('payment_terms_days', $supplier->payment_terms_days) }}" class="form-control @error('payment_terms_days') is-invalid @enderror">
                                @error('payment_terms_days') <div class="invalid-feedback">{{ $message }}</div> @else <div class="form-text">Bills fall due this many days after delivery. 0 = cash on delivery.</div> @enderror
                            </div>
                            <div class="col-sm-6">
                                <label for="opening_balance" class="form-label">Opening balance owed (Rs.)</label>
                                @if ($balanceLocked)
                                    <input type="text" class="form-control" value="{{ \App\Support\Format::money($supplier->opening_balance) }}" readonly>
                                    <div class="form-text">Fixed once the account has entries.</div>
                                @else
                                    <input type="text" inputmode="decimal" id="opening_balance" name="opening_balance" value="{{ old('opening_balance', $editing ? \App\Support\Format::meter($supplier->opening_balance) : '0') }}" class="form-control ft-num @error('opening_balance') is-invalid @enderror">
                                    @error('opening_balance') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                @endif
                            </div>
                            <div class="col-12">
                                <label for="notes" class="form-label">Notes</label>
                                <input type="text" id="notes" name="notes" value="{{ old('notes', $supplier->notes) }}" maxlength="500" class="form-control @error('notes') is-invalid @enderror">
                            </div>
                            @if ($editing)
                                <div class="col-12">
                                    <div class="form-check form-switch">
                                        <input type="hidden" name="is_active" value="0">
                                        <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" @checked(old('is_active', $supplier->is_active))>
                                        <label class="form-check-label" for="is_active">Active supplier</label>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ $editing ? route('suppliers.show', $supplier) : route('suppliers.index') }}" class="btn btn-light">Cancel</a>
                            <button type="submit" class="btn btn-warning px-4" :disabled="submitting">{{ $editing ? 'Save supplier' : 'Add supplier' }}</button>
                        </div>
                    </form>

                    @if ($editing && ! $balanceLocked)
                        <form method="POST" action="{{ route('suppliers.destroy', $supplier) }}" class="mt-4 pt-4 border-top text-end" x-data="{ sure: false }">
                            @csrf
                            @method('DELETE')
                            <button type="button" class="btn btn-outline-danger" x-show="! sure" @click="sure = true"><i class="bi bi-trash3 me-1"></i> Delete supplier</button>
                            <span x-show="sure" x-cloak><span class="d-inline-flex gap-2"><button type="submit" class="btn btn-danger">Yes, delete</button><button type="button" class="btn btn-light" @click="sure = false">Keep</button></span></span>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
