<x-layouts.app :title="$employee->name" :subtitle="$employee->designation.' · since '.$employee->joined_on->format('M Y')">
    <div class="row g-4">
        <div class="col-lg-5 col-xl-4">
            <div class="card mb-4">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        @if ($photo = $employee->documentUrl('photo'))
                            <a href="{{ $photo }}" target="_blank" rel="noopener" title="Open photo"><x-employee-avatar :employee="$employee" size="xl" /></a>
                        @else
                            <x-employee-avatar :employee="$employee" size="xl" />
                        @endif
                        <div style="min-width: 0;">
                            <div class="fw-bold fs-5 text-truncate">{{ $employee->name }}</div>
                            <div class="text-secondary">{{ $employee->designation }}</div>
                            @unless ($employee->is_active)
                                <span class="badge text-bg-dark mt-1">Inactive</span>
                            @endunless
                        </div>
                    </div>

                    <dl class="row mb-0 small">
                        <dt class="col-5 fw-normal text-secondary">Monthly salary</dt>
                        <dd class="col-7 text-end fw-bold">@money($employee->monthly_salary)</dd>
                        <dt class="col-5 fw-normal text-secondary">CNIC</dt>
                        <dd class="col-7 text-end ft-num">{{ $employee->cnic_number ?? '—' }}</dd>
                        <dt class="col-5 fw-normal text-secondary">Joined</dt>
                        <dd class="col-7 text-end">{{ $employee->joined_on->format('d M Y') }}</dd>
                        <dt class="col-5 fw-normal text-secondary">Phone</dt>
                        <dd class="col-7 text-end">
                            @if ($employee->phone)
                                <a href="tel:{{ preg_replace('/[^0-9+]/', '', $employee->phone) }}">{{ $employee->phone }}</a>
                            @else
                                —
                            @endif
                        </dd>
                        @if ($employee->notes)
                            <dt class="col-5 fw-normal text-secondary">Notes</dt>
                            <dd class="col-7 text-end">{{ $employee->notes }}</dd>
                        @endif
                    </dl>

                    @if ($employee->hasDocument('cnic_front') || $employee->hasDocument('cnic_back'))
                        <div class="row g-2 mt-3">
                            @foreach (['cnic_front' => 'CNIC front', 'cnic_back' => 'CNIC back'] as $document => $label)
                                @if ($url = $employee->documentUrl($document))
                                    <div class="col-6">
                                        <a href="{{ $url }}" target="_blank" rel="noopener" class="doc-thumb" title="Open {{ $label }}">
                                            <img src="{{ $url }}" alt="{{ $label }}" loading="lazy">
                                        </a>
                                        <div class="small text-secondary text-center mt-1">{{ $label }}</div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @else
                        <div class="small text-secondary mt-3"><i class="bi bi-person-vcard me-1"></i>No CNIC copy uploaded yet.</div>
                    @endif

                    <a href="{{ route('employees.edit', $employee) }}" class="btn btn-outline-dark w-100 mt-4"><i class="bi bi-pencil me-1"></i> Edit details</a>
                </div>
            </div>

            <div class="money-panel p-4 mb-4">
                <div class="small opacity-75">Advance to recover</div>
                <div class="money-panel-amount">@money($outstanding)</div>
                <div class="small opacity-75 mt-1">Deducted automatically when the next salary is prepared.</div>
            </div>

            @if ($employee->is_active)
                <div class="card">
                    <div class="card-header">Give an advance</div>
                    <div class="card-body p-4">
                        <form method="POST" action="{{ route('employees.advances.store', $employee) }}" novalidate x-data="{ submitting: false }" @submit="submitting = true">
                            @csrf
                            <div class="mb-3">@include('partials.business-date', ['date' => $businessDate])</div>
                            <div class="row g-3">
                                <div class="col-sm-6 col-lg-12 col-xl-6">
                                    <label for="amount" class="form-label">Amount</label>
                                    <div class="input-group has-validation">
                                        <span class="input-group-text">Rs.</span>
                                        <input type="text" inputmode="decimal" id="amount" name="amount" value="{{ old('amount') }}"
                                               class="form-control ft-num @error('amount') is-invalid @enderror" required>
                                        @error('amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                                <div class="col-sm-6 col-lg-12 col-xl-6">
                                    <label for="payment_method" class="form-label">Paid by</label>
                                    <select id="payment_method" name="payment_method" class="form-select @error('payment_method') is-invalid @enderror">
                                        @foreach ($paymentMethods as $method)
                                            <option value="{{ $method->value }}" @selected(old('payment_method', 'cash') === $method->value)>{{ $method->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label for="notes" class="form-label">Reason <span class="text-secondary fw-normal small">(optional)</span></label>
                                    <input type="text" id="notes" name="notes" value="{{ old('notes') }}" maxlength="500" class="form-control" placeholder="e.g. Family need">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-warning w-100 mt-3" :disabled="submitting">
                                <span x-show="submitting" x-cloak class="spinner-border spinner-border-sm me-2"></span>
                                Give advance
                            </button>
                            <div class="form-text">Saved as a staff advance expense for today, so it is counted in the cash closing.</div>
                        </form>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-lg-7 col-xl-8">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    Salary history
                    <a href="{{ route('payroll.index') }}" class="small fw-normal text-decoration-none">Payroll</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th class="text-end">Earned</th>
                                <th class="text-end">Deductions</th>
                                <th class="text-end">Net paid</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($employee->salarySlips as $slip)
                                <tr>
                                    <td class="fw-semibold text-nowrap">{{ $slip->month->format('F Y') }}</td>
                                    <td class="text-end ft-num">@money($slip->grossSalary())</td>
                                    <td class="text-end ft-num text-danger">@money($slip->totalDeductions() - (float) $slip->absence_deduction)</td>
                                    <td class="text-end ft-num fw-bold">@money($slip->net_salary)</td>
                                    <td>
                                        @if ($slip->isPaid())
                                            <span class="badge text-bg-success">Paid {{ $slip->paid_on->format('d M') }}</span>
                                        @else
                                            <span class="badge text-bg-warning">Pending</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('payroll.slips.show', $slip) }}" class="btn btn-sm btn-light">Slip</a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="empty-state py-4"><i class="bi bi-cash-coin"></i>No salary sheets yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Advances given</div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th class="text-end">Amount</th>
                                <th>Paid by</th>
                                <th>Reason</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($employee->advances as $advance)
                                <tr>
                                    <td class="text-nowrap">{{ $advance->business_date->format('d M Y') }}</td>
                                    <td class="text-end ft-num fw-bold">@money($advance->amount)</td>
                                    <td class="small">{{ $advance->expense->payment_method->label() }}<br><span class="text-secondary">{{ $advance->recorder?->name ?? '—' }}</span></td>
                                    <td class="small text-secondary">{{ $advance->notes ?? '—' }}</td>
                                    <td class="text-end">
                                        @if ($advance->business_date->isSameDay($businessDate))
                                            <form method="POST" action="{{ route('advances.destroy', $advance) }}" x-data="{ sure: false }">
                                                @csrf
                                                @method('DELETE')
                                                <button type="button" class="btn btn-sm btn-link text-danger" x-show="! sure" @click="sure = true">Delete</button>
                                                <span x-show="sure" x-cloak><span class="d-flex gap-1 justify-content-end">
                                                    <button type="submit" class="btn btn-sm btn-danger">Yes</button>
                                                    <button type="button" class="btn btn-sm btn-light" @click="sure = false">No</button>
                                                </span></span>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="empty-state py-4"><i class="bi bi-arrow-repeat"></i>No advances given.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
