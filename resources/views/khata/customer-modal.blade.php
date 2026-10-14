{{-- "New customer" popup (owner only), used on the Khata register pages. --}}
@if (auth()->user()->isOwner())
    @php($reopen = old('_modal') === 'customer')
    <div x-data x-init="@js($reopen) && $nextTick(() => bootstrap.Modal.getOrCreateInstance(document.getElementById('newCustomerModal')).show())">
        <template x-teleport="body">
            <div class="modal fade khata-modal" id="newCustomerModal" tabindex="-1" aria-labelledby="newCustomerTitle" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <form class="modal-content" method="POST" action="{{ route('customers.store') }}" novalidate x-data="{ submitting: false }" @submit="submitting = true">
                        @csrf
                        <input type="hidden" name="_modal" value="customer">
                        <div class="modal-header">
                            <div>
                                <h2 class="modal-title h5 mb-0" id="newCustomerTitle"><i class="bi bi-person-plus me-2"></i>New khata customer</h2>
                                <div class="small text-secondary">Opens a credit account. Credit sales and payments are added to it later.</div>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label for="nc_name" class="form-label fw-semibold">Customer / company name</label>
                                    <input type="text" id="nc_name" name="name" value="{{ $reopen ? old('name') : '' }}" maxlength="100" required
                                           class="form-control form-control-lg @if ($reopen) @error('name') is-invalid @enderror @endif" placeholder="e.g. Al-Madina Transport">
                                    @if ($reopen) @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror @endif
                                </div>
                                <div class="col-sm-6">
                                    <label for="nc_phone" class="form-label">Phone</label>
                                    <input type="text" id="nc_phone" name="phone" value="{{ $reopen ? old('phone') : '' }}" maxlength="30"
                                           class="form-control @if ($reopen) @error('phone') is-invalid @enderror @endif" placeholder="03xx-xxxxxxx">
                                    @if ($reopen) @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror @endif
                                </div>
                                <div class="col-sm-6">
                                    <label for="nc_vehicle" class="form-label">Main vehicle no.</label>
                                    <input type="text" id="nc_vehicle" name="vehicle_number" value="{{ $reopen ? old('vehicle_number') : '' }}" maxlength="30" class="form-control text-uppercase" placeholder="e.g. LES-778">
                                </div>
                                <div class="col-sm-6">
                                    <label for="nc_limit" class="form-label">Credit limit (Rs.)</label>
                                    <input type="text" inputmode="decimal" id="nc_limit" name="credit_limit" value="{{ $reopen ? old('credit_limit') : '' }}"
                                           class="form-control ft-num @if ($reopen) @error('credit_limit') is-invalid @enderror @endif" placeholder="No limit">
                                    @if ($reopen) @error('credit_limit') <div class="invalid-feedback">{{ $message }}</div> @enderror @endif
                                    <div class="form-text">Credit above the limit is blocked. Empty = no limit.</div>
                                </div>
                                <div class="col-sm-6">
                                    <label for="nc_opening" class="form-label">Already owes (Rs.)</label>
                                    <input type="text" inputmode="decimal" id="nc_opening" name="opening_balance" value="{{ $reopen ? old('opening_balance', '0') : '0' }}"
                                           class="form-control ft-num @if ($reopen) @error('opening_balance') is-invalid @enderror @endif">
                                    @if ($reopen) @error('opening_balance') <div class="invalid-feedback">{{ $message }}</div> @enderror @endif
                                    <div class="form-text">Old balance from the paper khata, if any.</div>
                                </div>
                                <div class="col-12">
                                    <input type="text" name="address" value="{{ $reopen ? old('address') : '' }}" maxlength="255" class="form-control" placeholder="Address (optional)">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-warning px-4" :disabled="submitting"><i class="bi bi-person-check me-1"></i>Open khata</button>
                        </div>
                    </form>
                </div>
            </div>
        </template>
    </div>
@endif
