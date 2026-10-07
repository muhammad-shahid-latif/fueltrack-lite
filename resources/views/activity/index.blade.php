<x-layouts.app title="Audit trail" subtitle="Who created, changed or deleted what, and when">
    <form method="GET" class="card mb-4">
        <div class="card-body d-flex flex-wrap align-items-end gap-2 p-3">
            <div>
                <label for="from" class="form-label small mb-1">From</label>
                <input type="date" id="from" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control">
            </div>
            <div>
                <label for="to" class="form-label small mb-1">To</label>
                <input type="date" id="to" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control">
            </div>
            <div>
                <label for="module" class="form-label small mb-1">Module</label>
                <select id="module" name="module" class="form-select">
                    <option value="">All modules</option>
                    @foreach ($modules as $class => $label)
                        <option value="{{ $class }}" @selected(($filters['module'] ?? null) === $class)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="event" class="form-label small mb-1">Action</label>
                <select id="event" name="event" class="form-select">
                    <option value="">Any</option>
                    @foreach (['created' => 'Created', 'updated' => 'Changed', 'deleted' => 'Deleted'] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['event'] ?? null) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="user" class="form-label small mb-1">User</label>
                <select id="user" name="user" class="form-select">
                    <option value="">Everyone</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected(($filters['user'] ?? null) == $user->id)>{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-dark"><i class="bi bi-funnel me-1"></i> Filter</button>
            @if (array_filter($filters))<a href="{{ route('activity.index') }}" class="btn btn-light">Clear</a>@endif
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>When</th><th>User</th><th>Action</th><th>Record</th><th>Changes</th></tr></thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr x-data="{ open: false }">
                            <td class="text-nowrap">{{ $log->created_at->format('d M Y') }}<div class="small text-secondary">{{ $log->created_at->format('h:i:s A') }}</div></td>
                            <td class="text-nowrap">{{ $log->user?->name ?? 'System' }}@if ($log->ip_address)<div class="small text-secondary">{{ $log->ip_address }}</div>@endif</td>
                            <td><span class="badge {{ $log->eventBadge() }}">{{ ucfirst($log->event) }}</span></td>
                            <td><span class="fw-semibold d-block">{{ $log->description }}</span><span class="small text-secondary">{{ $log->module() }} #{{ $log->subject_id }}</span></td>
                            <td class="audit-changes" style="min-width: 260px;">
                                @php($rows = $log->changeRows())
                                @if ($log->event === 'updated')
                                    @foreach ($rows as $change)
                                        <div><code>{{ $change['field'] }}</code> <span class="diff-old">{{ is_scalar($change['old']) ? $change['old'] : json_encode($change['old']) }}</span> → <span class="diff-new">{{ is_scalar($change['new']) ? $change['new'] : json_encode($change['new']) }}</span></div>
                                    @endforeach
                                @elseif ($rows !== [])
                                    <button type="button" class="btn btn-sm btn-light" @click="open = ! open"><span x-text="open ? 'Hide' : 'Show'"></span> {{ count($rows) }} fields</button>
                                    <div x-show="open" x-cloak class="mt-2">
                                        @foreach ($rows as $change)
                                            @php($value = $log->event === 'deleted' ? $change['old'] : $change['new'])
                                            <div><code>{{ $change['field'] }}</code> <span @class(['diff-old' => $log->event === 'deleted', 'diff-new' => $log->event !== 'deleted'])>{{ is_scalar($value) ? $value : json_encode($value) }}</span></div>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty-state"><i class="bi bi-shield-lock"></i>No activity matches.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $logs->links() }}</div>
</x-layouts.app>
