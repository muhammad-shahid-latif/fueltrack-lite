<x-layouts.app title="Daily closing" subtitle="Each closing locks the day and records the cash count">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <span></span>
        <a href="{{ route('closings.create') }}" class="btn btn-warning">
            <i class="bi bi-journal-check me-1"></i> Close today
        </a>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th class="text-end">Total sales</th>
                        <th class="text-end">Expenses</th>
                        <th class="text-end">Expected cash</th>
                        <th class="text-end">Actual cash</th>
                        <th class="text-end">Difference</th>
                        <th class="d-none d-md-table-cell">Closed by</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($closings as $closing)
                        <tr>
                            <td class="fw-semibold text-nowrap">{{ $closing->business_date->format('D, d M Y') }}</td>
                            <td class="text-end ft-num">@money($closing->total_sales)</td>
                            <td class="text-end ft-num">@money($closing->total_expenses)</td>
                            <td class="text-end ft-num">@money($closing->expected_cash)</td>
                            <td class="text-end ft-num">@money($closing->actual_cash)</td>
                            <td class="text-end">
                                @if ($closing->hasCashDifference())
                                    <span @class(['badge', 'text-bg-danger' => $closing->difference < 0, 'text-bg-warning' => $closing->difference > 0])>
                                        {{ $closing->difference > 0 ? '+' : '' }}@money($closing->difference)
                                    </span>
                                @else
                                    <span class="badge text-bg-success">Balanced</span>
                                @endif
                            </td>
                            <td class="d-none d-md-table-cell small text-secondary">{{ $closing->closer?->name ?? '—' }}</td>
                            <td class="text-end">
                                <a href="{{ route('closings.show', $closing) }}" class="btn btn-sm btn-outline-dark">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="empty-state"><i class="bi bi-journal-check"></i>No days closed yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $closings->links() }}</div>
</x-layouts.app>
