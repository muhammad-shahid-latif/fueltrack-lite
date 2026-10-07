<x-layouts.app title="Miscellaneous register" subtitle="Utilities, maintenance and operational costs, with full history">
    @php($groupIcons = ['Utilities' => ['lightning-charge', 'cash'], 'Maintenance' => ['tools', 'violet'], 'Operational' => ['gear-wide-connected', 'teal'], 'Payroll' => ['cash-coin', 'expense']])
    @php($periodTotal = $byGroup->sum())

    <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
        <form method="GET" action="{{ route('expenses.index') }}" class="d-flex flex-wrap align-items-end gap-2">
            <div>
                <label for="from" class="form-label small mb-1">From</label>
                <input type="date" id="from" name="from" value="{{ $from->toDateString() }}" max="{{ today()->toDateString() }}" class="form-control">
            </div>
            <div>
                <label for="to" class="form-label small mb-1">To</label>
                <input type="date" id="to" name="to" value="{{ $to->toDateString() }}" max="{{ today()->toDateString() }}" class="form-control">
            </div>
            <div>
                <label for="category" class="form-label small mb-1">Category</label>
                <select id="category" name="category" class="form-select">
                    <option value="">All categories</option>
                    @foreach (\App\Enums\ExpenseCategory::cases() as $category)
                        <option value="{{ $category->value }}" @selected(($filters['category'] ?? null) === $category->value)>{{ $category->group() }} · {{ $category->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="q" class="form-label small mb-1">Search</label>
                <input type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="Description…">
            </div>
            @if (! empty($filters['group']))<input type="hidden" name="group" value="{{ $filters['group'] }}">@endif
            <button type="submit" class="btn btn-dark" aria-label="Filter"><i class="bi bi-funnel"></i></button>
            <div class="seg">
                @foreach ([0 => 'Today', 6 => '7 days', 29 => '30 days'] as $days => $label)
                    <a href="{{ route('expenses.index', ['from' => today()->subDays($days)->toDateString(), 'to' => today()->toDateString()]) }}" @class(['active' => $from->isSameDay(today()->subDays($days)) && $to->isToday()])>{{ $label }}</a>
                @endforeach
            </div>
        </form>

        <a href="{{ route('expenses.create') }}" class="btn btn-warning"><i class="bi bi-plus-lg me-1"></i> Add expense</a>
    </div>

    @error('to') <div class="alert alert-danger">{{ $message }}</div> @enderror

    <div class="row g-3 mb-4">
        @foreach ($byGroup as $group => $total)
            @php($active = ($filters['group'] ?? null) === $group)
            <div class="col-sm-6 col-xl-3">
                <a href="{{ route('expenses.index', ['from' => $from->toDateString(), 'to' => $to->toDateString(), ...($active ? [] : ['group' => $group])]) }}" class="text-decoration-none text-reset d-block h-100">
                    <x-stat-tile :label="$group" :value="\App\Support\Format::money(round($total))" :icon="$groupIcons[$group][0]" :tone="$groupIcons[$group][1]"
                                 :hint="($periodTotal > 0 ? round($total / $periodTotal * 100).'% of spend' : '—').($active ? ' · filtered' : '')"
                                 :style="$active ? 'outline: 3px solid var(--ft-amber); outline-offset: 3px;' : null" />
                </a>
            </div>
        @endforeach
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-4">
            <div class="card h-100">
                <div class="card-header">By category</div>
                <div class="card-body">
                    @php($maxCat = max(1, (float) ($byCategory->max('total') ?? 1)))
                    @forelse ($byCategory as $row)
                        <a href="{{ route('expenses.index', ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'category' => $row->category->value]) }}" class="d-flex align-items-center gap-3 mb-3 text-decoration-none text-reset">
                            <span class="icon-chip"><i class="bi bi-{{ $row->category->icon() }}"></i></span>
                            <span class="flex-grow-1" style="min-width: 0;">
                                <span class="d-flex justify-content-between small"><span class="fw-semibold text-truncate">{{ $row->category->label() }}</span><span class="ft-num fw-bold">@money($row->total)</span></span>
                                <span class="share-bar d-block mt-1"><span style="width: {{ round($row->total / $maxCat * 100) }}%"></span></span>
                            </span>
                        </a>
                    @empty
                        <div class="text-secondary">No expenses in this period.</div>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-xl-8">
            <div class="card h-100">
                <div class="card-header d-flex">
                    <span>History</span>
                    <span class="small fw-normal">Showing <strong class="ft-num">@money($filteredTotal)</strong></span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th class="text-end">Amount</th>
                                <th>Paid by</th>
                                <th class="d-none d-md-table-cell">Recorded by</th>
                                @if (auth()->user()->isOwner())<th></th>@endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($expenses as $expense)
                                <tr>
                                    <td class="text-nowrap">{{ $expense->business_date->format('d M Y') }}</td>
                                    <td class="text-nowrap"><span class="d-inline-flex align-items-center gap-2"><span class="icon-chip" style="width: 28px; height: 28px;"><i class="bi bi-{{ $expense->category->icon() }}"></i></span><span><span class="d-block small fw-semibold">{{ $expense->category->label() }}</span><span class="small text-secondary">{{ $expense->category->group() }}</span></span></span></td>
                                    <td>
                                        {{ $expense->description }}
                                        @if ($expense->isPayroll() && auth()->user()->isOwner())
                                            @if ($expense->salarySlip)
                                                <a href="{{ route('payroll.slips.show', $expense->salarySlip) }}" class="badge text-bg-light border text-decoration-none ms-1"><i class="bi bi-cash-coin me-1"></i>Payroll</a>
                                            @elseif ($expense->salaryAdvance)
                                                <a href="{{ route('employees.show', $expense->salaryAdvance->employee_id) }}" class="badge text-bg-light border text-decoration-none ms-1"><i class="bi bi-person me-1"></i>Advance</a>
                                            @endif
                                        @endif
                                        @if ($expense->notes)
                                            <div class="small text-secondary">{{ $expense->notes }}</div>
                                        @endif
                                    </td>
                                    <td class="text-end ft-num fw-bold">@money($expense->amount)</td>
                                    <td>{{ $expense->payment_method->label() }}</td>
                                    <td class="d-none d-md-table-cell small text-secondary">{{ $expense->recorder?->name ?? '—' }}</td>
                                    @if (auth()->user()->isOwner())
                                        <td class="text-end text-nowrap">
                                            @unless ($expense->isPayroll())
                                                <a href="{{ route('expenses.edit', $expense) }}" class="btn btn-sm btn-light" aria-label="Edit"><i class="bi bi-pencil"></i></a>
                                            @endunless
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="empty-state"><i class="bi bi-wallet2"></i>No expenses match.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-3">{{ $expenses->links() }}</div>
</x-layouts.app>
