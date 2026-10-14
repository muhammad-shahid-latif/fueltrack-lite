{{--
    "Receive payment" popup for any customer, used on the Khata register pages.
    $payCustomers: CreditService::pickerList(); $paymentMethods: PaymentMethod cases.
--}}
@php($reopen = old('_modal') === 'payment')
<div x-data="receivePayment(@js($payCustomers), @js(route('customers.payments.store', '__ID__')), @js($reopen ? (int) old('customer_ref') : null), @js($reopen ? old('amount', '') : ''))">
    <template x-teleport="body">
        <div class="modal fade khata-modal" id="receivePaymentModal" tabindex="-1" aria-labelledby="receivePaymentTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content" method="POST" :action="action" novalidate @submit="if (!ready) $event.preventDefault()">
                    @csrf
                    <input type="hidden" name="_modal" value="payment">
                    <input type="hidden" name="customer_ref" :value="customerId">
                    <div class="modal-header">
                        <h2 class="modal-title h5 mb-0" id="receivePaymentTitle"><i class="bi bi-cash-coin me-2 text-success"></i>Receive payment</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <label for="pay_customer" class="form-label fw-semibold">Customer</label>
                        <select id="pay_customer" class="form-select form-select-lg" x-model.number="customerId">
                            <option value="">Choose a customer…</option>
                            <template x-for="c in customers" :key="c.id">
                                <option :value="c.id" :selected="c.id === customerId" x-text="c.name + (c.balance > 0 ? ' — owes ' + money(c.balance) : '')"></option>
                            </template>
                        </select>

                        <div class="khata-balance mt-3" x-show="customer" x-cloak>
                            <span>Owes now</span>
                            <strong class="ft-num" x-text="customer ? money(customer.balance) : ''"></strong>
                        </div>

                        <label for="pay_amount_modal" class="form-label fw-semibold mt-3">Amount received (Rs.)</label>
                        <div class="input-group input-group-lg">
                            <input type="text" inputmode="decimal" id="pay_amount_modal" name="amount" x-model="amount" autocomplete="off"
                                   class="form-control ft-num @if ($reopen) @error('amount') is-invalid @enderror @endif" placeholder="0">
                            <button type="button" class="btn btn-light" :disabled="!customer || customer.balance <= 0" @click="amount = String(customer.balance)">Full</button>
                        </div>
                        @if ($reopen)
                            @error('amount') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        @endif
                        <div class="small mt-1 text-secondary" x-show="customer && value > 0" x-text="customer ? 'Balance after: ' + money(Math.round((customer.balance - value) * 100) / 100) : ''"></div>

                        <label class="form-label fw-semibold mt-3 d-block">Received by</label>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach ($paymentMethods as $method)
                                <input type="radio" class="btn-check" name="payment_method" id="kp_{{ $method->value }}" value="{{ $method->value }}" @checked(($reopen ? old('payment_method') : 'cash') === $method->value)>
                                <label class="btn btn-outline-dark" for="kp_{{ $method->value }}">{{ $method->label() }}</label>
                            @endforeach
                        </div>
                        <input type="text" name="reference" maxlength="50" value="{{ $reopen ? old('reference') : '' }}" class="form-control mt-3" placeholder="Cheque / receipt no. (optional)">
                        <div class="form-text"><i class="bi bi-info-circle me-1"></i>Cash received goes into today's drawer at closing.</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success px-4" :disabled="!ready"><i class="bi bi-check2-circle me-1"></i>Save payment</button>
                    </div>
                </form>
            </div>
        </div>
    </template>
</div>
