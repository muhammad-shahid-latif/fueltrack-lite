<x-layouts.app title="Employees" :subtitle="$activeCount.' active staff on the payroll'">
    <div class="d-flex flex-wrap justify-content-end gap-2 mb-3">
        <a href="{{ route('payroll.index') }}" class="btn btn-light"><i class="bi bi-cash-coin me-1"></i> Payroll</a>
        <a href="{{ route('employees.create') }}" class="btn btn-warning"><i class="bi bi-person-plus me-1"></i> Add employee</a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <x-stat-tile label="Active staff" :value="$activeCount" icon="people-fill" tone="teal" />
        </div>
        <div class="col-md-4">
            <x-stat-tile label="Monthly salary bill" :value="\App\Support\Format::money($monthlyBill)" icon="cash-coin" tone="violet" hint="Basic salaries of active staff" />
        </div>
        <div class="col-md-4">
            <x-stat-tile label="Advances to recover" :value="\App\Support\Format::money($advances->sum())" icon="arrow-repeat" tone="cash" hint="Deducted from the next salaries" />
        </div>
    </div>

    <div class="row g-3">
        @forelse ($employees as $employee)
            @php
                $slip = $employee->salarySlips->first();
                $owed = $advances->get($employee->id, 0);
            @endphp
            <div class="col-md-6 col-xl-4">
                <a href="{{ route('employees.show', $employee) }}" @class(['card staff-card h-100 text-reset text-decoration-none', 'is-inactive' => ! $employee->is_active])>
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <x-employee-avatar :employee="$employee" />
                            <span class="flex-grow-1" style="min-width: 0;">
                                <span class="d-block fw-bold text-truncate">{{ $employee->name }}</span>
                                <span class="small text-secondary">{{ $employee->designation }}</span>
                            </span>
                            @unless ($employee->is_active)
                                <span class="badge text-bg-dark">Inactive</span>
                            @endunless
                        </div>

                        <div class="d-flex justify-content-between align-items-end">
                            <div>
                                <div class="small text-secondary">Monthly salary</div>
                                <div class="staff-figure">@money($employee->monthly_salary)</div>
                            </div>
                            <div class="text-end">
                                @if ($slip)
                                    <span @class(['badge', 'text-bg-success' => $slip->isPaid(), 'text-bg-warning' => ! $slip->isPaid()])>
                                        {{ today()->format('M') }}: {{ $slip->status->label() }}
                                    </span>
                                @elseif ($employee->is_active)
                                    <span class="badge text-bg-light border">{{ today()->format('M') }}: not prepared</span>
                                @endif
                                @if ($owed > 0)
                                    <div class="small text-danger mt-1">Advance @money($owed)</div>
                                @endif
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        @empty
            <div class="col-12">
                <div class="card">
                    <div class="empty-state">
                        <i class="bi bi-people"></i>
                        No employees yet. Add your staff to run payroll.
                        <div class="mt-3"><a href="{{ route('employees.create') }}" class="btn btn-warning">Add employee</a></div>
                    </div>
                </div>
            </div>
        @endforelse
    </div>
</x-layouts.app>
