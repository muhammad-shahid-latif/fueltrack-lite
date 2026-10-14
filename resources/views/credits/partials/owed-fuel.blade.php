{{--
    Credit slips with fuel still to give, plus the "Give fuel" popup.
    $owed: Collection of CustomerCredit (CreditService::openOwed()); $showCustomer: add the customer column.
--}}
@php($showCustomer = $showCustomer ?? true)
@if ($owed->isNotEmpty())
    @php($owedLiters = $owed->sum(fn ($credit) => $credit->owedRemaining()))
    @php($owedValue = $owed->sum(fn ($credit) => round($credit->owedRemaining() * (float) $credit->rate, 2)))
    <div class="card owed-card mb-4" x-data="owedGive()">
        <div class="card-header d-flex flex-wrap align-items-center gap-2">
            <span class="owed-card-icon"><i class="bi bi-hourglass-split"></i></span>
            <div>
                <div class="fw-bold">Fuel still to give</div>
                <div class="small text-secondary fw-normal">Billed on credit slips, not filled yet. Give it from here when the vehicle comes back.</div>
            </div>
            <div class="ms-auto text-end">
                <div class="owed-total ft-num">@liters($owedLiters)</div>
                <div class="small text-secondary ft-num">@money($owedValue) · {{ $owed->count() }} {{ str('slip')->plural($owed->count()) }}</div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Slip</th>
                        @if ($showCustomer)<th>Customer</th>@endif
                        <th>Fuel</th>
                        <th class="text-end">On slip</th>
                        <th class="text-end">Given</th>
                        <th class="text-end">Still owed</th>
                        <th class="text-end d-print-none"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($owed as $credit)
                        @php($remaining = $credit->owedRemaining())
                        @php($days = (int) $credit->business_date->diffInDays(\App\Support\BusinessClock::today()))
                        <tr>
                            <td class="text-nowrap">
                                <div class="fw-semibold">{{ $credit->business_date->format('d M Y') }}</div>
                                <div class="small text-secondary">
                                    {{ collect([$credit->slip_number ? 'Slip '.$credit->slip_number : null, $credit->vehicle_number])->filter()->join(' · ') ?: '—' }}
                                </div>
                            </td>
                            @if ($showCustomer)
                                <td><a href="{{ route('customers.show', $credit->customer) }}" class="fw-semibold text-decoration-none">{{ $credit->customer->name }}</a></td>
                            @endif
                            <td class="{{ \App\Support\Grade::cssClass($credit->fuelProduct) }}"><span class="grade-dot me-1"></span>{{ $credit->fuelProduct?->name }}</td>
                            <td class="text-end ft-num">@liters($credit->quantity)</td>
                            <td class="text-end ft-num text-success">@liters(round((float) $credit->quantity - $remaining, 2))</td>
                            <td class="text-end">
                                <span class="owed-pill ft-num">@liters($remaining)</span>
                                <div class="small text-secondary mt-1 ft-num">@money(round($remaining * (float) $credit->rate, 2)){{ $days > 0 ? ' · '.$days.' '.str('day')->plural($days) : ' · today' }}</div>
                            </td>
                            <td class="text-end d-print-none">
                                <button type="button" class="btn btn-warning btn-sm fw-semibold text-nowrap"
                                        @click="open(@js([
                                            'action' => route('credits.owed.store', $credit),
                                            'customer' => $credit->customer->name,
                                            'fuel' => $credit->fuelProduct?->name,
                                            'slip' => $credit->business_date->format('d M Y').($credit->slip_number ? ' · Slip '.$credit->slip_number : ''),
                                            'vehicle' => $credit->vehicle_number,
                                            'remaining' => $remaining,
                                            'rate' => (float) $credit->rate,
                                        ]))">
                                    <i class="bi bi-fuel-pump-fill me-1"></i>Give fuel
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <template x-teleport="body">
            <div class="modal fade owed-modal" id="owedGiveModal" tabindex="-1" aria-labelledby="owedGiveTitle" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <form class="modal-content" method="POST" :action="slip.action" novalidate @submit="if (!valid) $event.preventDefault()">
                        @csrf
                        <div class="modal-header">
                            <div>
                                <h2 class="modal-title h5 mb-0" id="owedGiveTitle"><i class="bi bi-fuel-pump-fill me-2"></i>Give owed fuel</h2>
                                <div class="small text-secondary" x-text="`${slip.customer} · ${slip.slip}`"></div>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="owed-modal-owed">
                                <div>
                                    <div class="small text-secondary">Still owed on this slip</div>
                                    <div class="fs-3 fw-bold ft-num" x-text="liters(slip.remaining)"></div>
                                </div>
                                <div class="text-end small text-secondary">
                                    <div x-text="slip.fuel"></div>
                                    <div x-show="slip.vehicle" x-text="slip.vehicle"></div>
                                </div>
                            </div>

                            <label for="give_quantity" class="form-label fw-semibold mt-3">Liters given now</label>
                            <div class="input-group input-group-lg">
                                <input type="text" inputmode="decimal" id="give_quantity" name="give_quantity" x-model="quantity" autocomplete="off"
                                       class="form-control ft-num" :class="quantity !== '' && !valid && 'is-invalid'" placeholder="0">
                                <button type="button" class="btn btn-light" @click="quantity = String(slip.remaining)">All</button>
                            </div>
                            <div class="small mt-2" x-show="quantity !== ''">
                                <span x-show="!valid" class="text-danger" x-text="`Enter more than 0 and up to ${liters(slip.remaining)}.`"></span>
                                <span x-show="valid" class="text-secondary">
                                    <span class="fw-semibold text-success" x-text="liters(amount)"></span> now ·
                                    <span x-text="left > 0 ? liters(left) + ' will still be owed' : 'slip fully given'"></span>
                                </span>
                            </div>

                            <input type="text" name="notes" maxlength="500" class="form-control mt-3" placeholder="Note (optional)">
                            <div class="form-text"><i class="bi bi-info-circle me-1"></i>No new bill: the slip was charged already. Today's closing counts this fuel as credit, not cash.</div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-warning px-4" :disabled="!valid"><i class="bi bi-check2-circle me-1"></i>Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </template>
    </div>
@endif
