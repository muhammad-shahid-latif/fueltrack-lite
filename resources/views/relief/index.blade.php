<x-layouts.app title="Fuel relief" subtitle="PM Fuel Relief Scheme: the day's relief total, entered before closing">
    @php($isOwner = auth()->user()->isOwner())

    <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
        <form method="GET" action="{{ route('relief.index') }}" class="d-flex flex-wrap align-items-end gap-2">
            <div>
                <label for="date" class="form-label small mb-1">Day</label>
                <input type="date" id="date" name="date" value="{{ $date->toDateString() }}" max="{{ today()->toDateString() }}" class="form-control" onchange="this.form.submit()">
            </div>
            @unless ($date->isSameDay(\App\Support\BusinessClock::today()))
                <a href="{{ route('relief.index') }}" class="btn btn-light">Today</a>
            @endunless
        </form>
        <div class="d-flex flex-wrap gap-2">
            <span class="chip"><i class="bi bi-telephone-fill text-success"></i>Pump helpline <strong class="ft-num">9772</strong></span>
            @if ($isOpen)
                <a href="{{ route('closings.create', ['date' => $date->toDateString()]) }}" class="btn btn-dark"><i class="bi bi-journal-check me-1"></i>Go to daily closing</a>
            @else
                <span class="chip"><i class="bi bi-lock-fill text-secondary"></i>Day closed</span>
            @endif
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <x-stat-tile :label="'Relief on '.$date->format('d M')" :value="\App\Support\Format::money($totals['amount'])" icon="patch-check" tone="sales"
                         :hint="$totals['tokens'].' '.str('token')->plural($totals['tokens']).' · '.\App\Support\Format::liters($totals['liters'])" />
        </div>
        <div class="col-sm-6 col-xl-3">
            <x-stat-tile label="To claim from government" :value="\App\Support\Format::money($pending)" icon="hourglass-split" tone="cash" hint="Not received yet" />
        </div>
        <div class="col-sm-6 col-xl-3">
            <x-stat-tile label="Received so far" :value="\App\Support\Format::money($received)" icon="bank" tone="teal" hint="Paid through the State Bank" />
        </div>
        <div class="col-sm-6 col-xl-3">
            <x-stat-tile label="Relief per token" :value="\App\Support\Format::money($perToken)" icon="tags" tone="violet" hint="Whatever the customer fills" />
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-7">
            <div class="card h-100">
                <div class="card-header">
                    <div>
                        <div class="fw-bold">Relief sold on {{ $date->format('l, d M Y') }}</div>
                        <div class="small text-secondary fw-normal">Copy the day's tokens and relief amount from the government system before closing the day.</div>
                    </div>
                </div>
                <div class="card-body p-4 vstack gap-3">
                    @forelse ($rows as $row)
                        @php($entry = $row->entry)
                        @php($key = 'p'.$row->product->id)
                        @php($mine = (int) old('fuel_product_id') === $row->product->id)
                        @php($price = (float) ($entry->price ?? $row->product->selling_price))
                        <form method="POST" action="{{ route('relief.store') }}" novalidate class="relief-day"
                              x-data="{
                                  tokens: @js($mine ? old('tokens') : ($entry ? (string) $entry->tokens : '')),
                                  amount: @js($mine ? old('amount') : ($entry ? \App\Support\Format::input($entry->relief_amount) : '')),
                                  edited: @js($entry !== null || ($mine && old('amount') !== null)),
                                  perToken: {{ $perToken }},
                                  price: {{ $price }},
                                  sales: {{ $row->meterAmount }},
                                  submitting: false,
                                  num(v) { const n = parseFloat(v); return Number.isFinite(n) ? n : 0; },
                                  r(v) { return Math.round(v * 100) / 100; },
                                  get liters() { return this.price > 0 ? this.r(this.num(this.amount) / this.price) : 0; },
                                  get suggested() { return this.r((parseInt(this.tokens) || 0) * this.perToken); },
                                  get warning() { return this.sales > 0 && this.num(this.amount) > this.sales ? 'More than today\'s ' + this.money(this.sales) + ' of meter sales. Check the figures.' : ''; },
                                  onTokens() { if (! this.edited) this.amount = this.suggested ? String(this.suggested) : ''; },
                                  money(v) { return 'Rs. ' + v.toLocaleString('en-US', { maximumFractionDigits: 2 }); },
                              }"
                              @submit="if (num(amount) <= 0 || (parseInt(tokens) || 0) < 1) { $event.preventDefault(); return; } submitting = true">
                            @csrf
                            <input type="hidden" name="business_date" value="{{ $date->toDateString() }}">
                            <input type="hidden" name="fuel_product_id" value="{{ $row->product->id }}">
                            @if ($returnTo)<input type="hidden" name="return" value="{{ $returnTo }}">@endif

                            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                                <span class="relief-fuel-dot" style="--dot: var(--ft-{{ $row->product->code === 'diesel' ? 'diesel' : ($row->product->code === 'petrol' ? 'petrol' : 'hioctane') }})"></span>
                                <strong class="fs-6">{{ $row->product->name }}</strong>
                                <span class="small text-secondary">pump price @money($price)/L</span>
                                <span class="ms-auto chip"><i class="bi bi-speedometer2"></i>Meters today <strong class="ft-num">@liters($row->meterLiters) · @money($row->meterAmount)</strong></span>
                            </div>

                            @if ($isOpen)
                                <div class="row g-3">
                                    <div class="col-sm-4">
                                        <label class="form-label small" for="t_{{ $key }}">Tokens redeemed</label>
                                        <input type="text" inputmode="numeric" id="t_{{ $key }}" name="tokens" x-model="tokens" @input="onTokens()" autocomplete="off"
                                               class="form-control form-control-lg ft-num @if ($mine) @error('tokens') is-invalid @enderror @endif" placeholder="0">
                                    </div>
                                    <div class="col-sm-5">
                                        <label class="form-label small" for="a_{{ $key }}">Relief amount (Rs.)</label>
                                        <input type="text" inputmode="decimal" id="a_{{ $key }}" name="amount" x-model="amount" @input="edited = amount !== ''" autocomplete="off"
                                               class="form-control form-control-lg ft-num @if ($mine) @error('amount') is-invalid @enderror @endif" placeholder="0">
                                    </div>
                                    <div class="col-sm-3">
                                        <span class="form-label small d-block">Fuel worth</span>
                                        <div class="relief-liters"><strong class="ft-num" x-text="liters.toLocaleString('en-US') + ' L'">0 L</strong><span>at @money($price)</span></div>
                                    </div>
                                </div>
                                <div class="small text-secondary mt-2" x-show="suggested > 0 && num(amount) !== suggested" x-cloak>
                                    <span x-text="tokens + ' × ' + money(perToken) + ' = ' + money(suggested)"></span>
                                    <button type="button" class="btn btn-link btn-sm p-0 ms-1 align-baseline" @click="amount = String(suggested); edited = true">Use this</button>
                                </div>
                                @if ($mine)
                                    @error('tokens') <div class="small text-danger mt-2">{{ $message }}</div> @enderror
                                    @error('amount') <div class="small text-danger mt-2">{{ $message }}</div> @enderror
                                    @error('business_date') <div class="small text-danger mt-2">{{ $message }}</div> @enderror
                                @endif
                                <div class="small text-warning-emphasis mt-2" x-show="warning" x-cloak><i class="bi bi-exclamation-triangle me-1"></i><span x-text="warning"></span></div>

                                <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                                    @if ($entry)
                                        <span class="small text-secondary me-auto"><i class="bi bi-check2-circle text-success me-1"></i>Saved by {{ $entry->recorder?->name ?? '—' }} at {{ $entry->updated_at->format('h:i A') }}</span>
                                    @else
                                        <span class="me-auto"></span>
                                    @endif
                                    <button type="submit" class="btn btn-warning px-4" :disabled="submitting || num(amount) <= 0 || (parseInt(tokens) || 0) < 1">
                                        <i class="bi bi-check2-circle me-1"></i>{{ $entry ? 'Update' : 'Save' }}
                                    </button>
                                </div>
                            @elseif ($entry)
                                <div class="relief-calc">
                                    <div><span>Tokens</span><strong class="ft-num">{{ $entry->tokens }}</strong></div>
                                    <div class="is-govt"><span>Relief amount</span><strong class="ft-num">@money($entry->relief_amount)</strong></div>
                                    <div><span>Fuel worth</span><strong class="ft-num">@liters($entry->quantity)</strong></div>
                                </div>
                            @else
                                <div class="small text-secondary">No relief entered for this day.</div>
                            @endif
                        </form>
                        @if ($isOpen && $entry && ! $entry->isReceived())
                            <form method="POST" action="{{ route('relief.destroy', $entry) }}" x-data="{ sure: false }" class="text-end mt-n2">
                                @csrf
                                @method('DELETE')
                                <button type="button" class="btn btn-link btn-sm text-danger p-0" x-show="! sure" @click="sure = true">Remove {{ $row->product->name }} relief</button>
                                <span x-show="sure" x-cloak class="small">Remove it? <button type="submit" class="btn btn-sm btn-danger ms-1">Yes</button> <button type="button" class="btn btn-sm btn-light" @click="sure = false">No</button></span>
                            </form>
                        @endif
                    @empty
                        <div class="text-secondary">Add a fuel product first.</div>
                    @endforelse

                    @unless ($isOpen)
                        <div class="alert alert-secondary mb-0 small"><i class="bi bi-lock me-1"></i>This day is closed. The owner can reopen it from Daily closing to change the relief.</div>
                    @endunless
                </div>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="card mb-4">
                <div class="card-header">How it works</div>
                <div class="card-body small">
                    <ol class="relief-steps mb-0">
                        <li><strong>At the pump</strong> tokens are checked and cut in the <strong>government relief system</strong>, as usual.</li>
                        <li>Each token gives <strong>@money($perToken)</strong> off, whatever the customer fills: on a Rs. 600 fill the customer pays Rs. 100.</li>
                        <li><strong>Meter readings</strong> count all fuel at the pump price, relief fuel included.</li>
                        <li><strong>Before closing</strong>, enter the day's tokens and relief amount here. The liters are worked out at the pump price.</li>
                        <li><strong>Daily closing</strong> takes the relief amount off the expected cash, so the drawer matches. Mark the day received when the State Bank pays.</li>
                    </ol>
                </div>
            </div>

            @if ($isOwner)
                <form method="POST" action="{{ route('relief.settings') }}" novalidate class="card">
                    @csrf
                    @method('PUT')
                    <div class="card-header">Scheme setting</div>
                    <div class="card-body">
                        <label for="per_token" class="form-label">Relief per token (Rs.)</label>
                        <div class="input-group">
                            <input type="text" inputmode="decimal" id="per_token" name="per_token" value="{{ old('per_token', \App\Support\Format::input($perToken)) }}" class="form-control ft-num @error('per_token') is-invalid @enderror">
                            <button type="submit" class="btn btn-dark">Save</button>
                        </div>
                        @error('per_token') <div class="small text-danger mt-1">{{ $message }}</div> @enderror
                        <div class="form-text">Fills in the relief amount from the tokens. Each day's amount can still be changed.</div>
                    </div>
                </form>
            @endif
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex align-items-center">
                    <span>Claims to the government</span>
                    <span class="small fw-normal ms-auto">Pending <strong class="ft-num">@money($pending)</strong></span>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Day</th>
                                <th class="text-end d-none d-md-table-cell">Tokens</th>
                                <th class="text-end">Liters</th>
                                <th class="text-end">Relief</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($days as $day)
                                <tr x-data="{ open: false }">
                                    <td><a href="{{ route('relief.index', ['date' => $day->date->toDateString()]) }}" class="fw-semibold text-decoration-none">{{ $day->date->format('D d M Y') }}</a>
</td>
                                    <td class="text-end ft-num d-none d-md-table-cell">{{ $day->tokens ?: '—' }}</td>
                                    <td class="text-end ft-num">@liters($day->liters)</td>
                                    <td class="text-end ft-num fw-semibold">@money($day->amount)</td>
                                    <td>
                                        @if ($day->pending <= 0)
                                            <span class="badge text-bg-success"><i class="bi bi-check2 me-1"></i>Received {{ $day->received_on?->format('d M') }}</span>
                                            @if ($day->reference)<div class="small text-secondary">{{ $day->reference }}</div>@endif
                                            @if ($isOwner)
                                                <form method="POST" action="{{ route('relief.unreceive', $day->date->toDateString()) }}" class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-link btn-sm p-0 small text-secondary">Undo</button>
                                                </form>
                                            @endif
                                        @else
                                            <span class="badge text-bg-warning">Pending @money($day->pending)</span>
                                            @if ($isOwner)
                                                <button type="button" class="btn btn-sm btn-outline-success ms-1" x-show="! open" @click="open = true">Mark received</button>
                                                {{-- x-if, not x-show: Bootstrap's d-flex (!important) would override x-show. --}}
                                                <template x-if="open">
                                                <form method="POST" action="{{ route('relief.receive', $day->date->toDateString()) }}" class="d-flex flex-wrap gap-2 mt-2">
                                                    @csrf
                                                    <input type="date" name="received_on" value="{{ today()->toDateString() }}" min="{{ $day->date->toDateString() }}" max="{{ today()->toDateString() }}" class="form-control form-control-sm" style="width: 150px;" aria-label="Received on">
                                                    <input type="text" name="reference" maxlength="100" class="form-control form-control-sm" style="width: 150px;" placeholder="Bank ref. (optional)" aria-label="Reference">
                                                    <button type="submit" class="btn btn-sm btn-success">Save</button>
                                                    <button type="button" class="btn btn-sm btn-light" @click="open = false">Cancel</button>
                                                </form>
                                                </template>
                                            @endif
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-secondary py-4">Nothing to claim yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</x-layouts.app>
