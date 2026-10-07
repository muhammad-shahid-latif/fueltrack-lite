@php($editing = $customer->exists)
@php($balanceLocked = $editing && ($customer->credits()->exists() || $customer->payments()->exists()))
<x-layouts.app :title="$editing ? 'Edit '.$customer->name : 'Add customer'" subtitle="Credit account">
    <div class="row justify-content-center">
        <div class="col-lg-9 col-xl-7">
            <div class="card">
                <div class="card-body p-4 p-md-5">
                    <form method="POST" action="{{ $editing ? route('customers.update', $customer) : route('customers.store') }}" novalidate x-data="{ submitting: false }" @submit="submitting = true">
                        @csrf
                        @if ($editing) @method('PUT') @endif

                        <div class="row g-3">
                            <div class="col-12">
                                <label for="name" class="form-label">Customer / company name</label>
                                <input type="text" id="name" name="name" value="{{ old('name', $customer->name) }}" maxlength="100" class="form-control form-control-lg @error('name') is-invalid @enderror" required>
                                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-sm-6">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="text" id="phone" name="phone" value="{{ old('phone', $customer->phone) }}" maxlength="30" class="form-control @error('phone') is-invalid @enderror">
                                @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-sm-6">
                                <label for="vehicle_number" class="form-label">Main vehicle no.</label>
                                <input type="text" id="vehicle_number" name="vehicle_number" value="{{ old('vehicle_number', $customer->vehicle_number) }}" maxlength="30" class="form-control text-uppercase @error('vehicle_number') is-invalid @enderror">
                            </div>
                            <div class="col-12">
                                <label for="address" class="form-label">Address</label>
                                <input type="text" id="address" name="address" value="{{ old('address', $customer->address) }}" maxlength="255" class="form-control">
                            </div>
                            <div class="col-sm-6">
                                <label for="credit_limit" class="form-label">Credit limit (Rs.)</label>
                                <input type="text" inputmode="decimal" id="credit_limit" name="credit_limit" value="{{ old('credit_limit', $customer->credit_limit !== null ? \App\Support\Format::meter($customer->credit_limit) : '') }}" class="form-control ft-num @error('credit_limit') is-invalid @enderror">
                                @error('credit_limit') <div class="invalid-feedback">{{ $message }}</div> @else <div class="form-text">Leave empty for no limit. Credit over the limit is blocked.</div> @enderror
                            </div>
                            <div class="col-sm-6">
                                <label for="opening_balance" class="form-label">Opening balance owed (Rs.)</label>
                                @if ($balanceLocked)
                                    <input type="text" class="form-control" value="{{ \App\Support\Format::money($customer->opening_balance) }}" readonly>
                                    <div class="form-text">Fixed once the account has entries.</div>
                                @else
                                    <input type="text" inputmode="decimal" id="opening_balance" name="opening_balance" value="{{ old('opening_balance', $editing ? \App\Support\Format::meter($customer->opening_balance) : '0') }}" class="form-control ft-num @error('opening_balance') is-invalid @enderror">
                                    @error('opening_balance') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                @endif
                            </div>
                            <div class="col-12">
                                <label for="notes" class="form-label">Notes</label>
                                <input type="text" id="notes" name="notes" value="{{ old('notes', $customer->notes) }}" maxlength="500" class="form-control">
                            </div>
                            @if ($editing)
                                <div class="col-12">
                                    <div class="form-check form-switch">
                                        <input type="hidden" name="is_active" value="0">
                                        <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" @checked(old('is_active', $customer->is_active))>
                                        <label class="form-check-label" for="is_active">Account open for credit</label>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ $editing ? route('customers.show', $customer) : route('customers.index') }}" class="btn btn-light">Cancel</a>
                            <button type="submit" class="btn btn-warning px-4" :disabled="submitting">{{ $editing ? 'Save customer' : 'Add customer' }}</button>
                        </div>
                    </form>

                    @if ($editing && ! $balanceLocked)
                        <form method="POST" action="{{ route('customers.destroy', $customer) }}" class="mt-4 pt-4 border-top text-end" x-data="{ sure: false }">
                            @csrf
                            @method('DELETE')
                            <button type="button" class="btn btn-outline-danger" x-show="! sure" @click="sure = true"><i class="bi bi-trash3 me-1"></i> Delete customer</button>
                            <span x-show="sure" x-cloak><span class="d-inline-flex gap-2"><button type="submit" class="btn btn-danger">Yes, delete</button><button type="button" class="btn btn-light" @click="sure = false">Keep</button></span></span>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
