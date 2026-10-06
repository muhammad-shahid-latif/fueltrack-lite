<x-layouts.app title="Expenses" subtitle="What the station spent, by category">
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
            <button type="submit" class="btn btn-dark"><i class="bi bi-funnel"></i></button>
        </form>

        <a href="{{ route('expenses.create') }}" class="btn btn-warning">
            <i class="bi bi-plus-lg me-1"></i> Add expense
        </a>
    </div>

    @error('to') <div class="alert alert-danger">{{ $message }}</div> @enderror

    <div class="row g-3 mb-4">
        <div class="col-lg-4">
            <x-stat-tile label="Total expenses" :value="\App\Support\Format::money($byCategory->sum('total'))" icon="wallet2" tone="expense"
                         :hint="$from->isSameDay($to) ? $from->format('d M Y') : $from->format('d M').' to '.$to->format('d M Y')" />
        </div>
        <div class="col-lg-8">
            <div class="card h-100"><div class="card-body p-4">
                <div class="small text-secondary fw-semibold mb-2">By category</div>
                @forelse ($byCategory as $row)
                    <span class="chip me-1 mb-2">
                        {{ \App\Enums\ExpenseCategory::from($row->category)->label() }}
                        <span class="ft-num">@money($row->total)</span>
                    </span>
                @empty
                    <span class="text-secondary">No expenses in this period.</span>
                @endforelse
            </div></div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th class="text-end">Amount</th>
                        <th>Payment</th>
                        <th class="d-none d-md-table-cell">Recorded by</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($expenses as $expense)
                        <tr>
                            <td class="text-nowrap">{{ $expense->business_date->format('d M Y') }}</td>
                            <td><span class="badge text-bg-light border fw-normal">{{ $expense->category->label() }}</span></td>
                            <td>
                                {{ $expense->description }}
                                @if ($expense->notes)
                                    <div class="small text-secondary">{{ $expense->notes }}</div>
                                @endif
                            </td>
                            <td class="text-end ft-num fw-bold">@money($expense->amount)</td>
                            <td>{{ $expense->payment_method->label() }}</td>
                            <td class="d-none d-md-table-cell small text-secondary">{{ $expense->recorder?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty-state"><i class="bi bi-wallet2"></i>No expenses in this period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $expenses->links() }}</div>
</x-layouts.app>
