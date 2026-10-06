<x-layouts.app title="Pumps and nozzles" :subtitle="$pumps->where('is_active', true)->count().' active pumps, '.$pumps->flatMap->nozzles->where('is_active', true)->count().' active nozzles'">
    @if (auth()->user()->isOwner())
        <div class="d-flex justify-content-end mb-3">
            <a href="{{ route('pumps.create') }}" class="btn btn-warning"><i class="bi bi-plus-lg me-1"></i> Add pump</a>
        </div>
    @endif

    <div class="row g-4">
        @forelse ($pumps as $pump)
            <div class="col-md-6 col-xl-4">
                <div @class(['card dispenser h-100', 'is-inactive' => ! $pump->is_active])>
                    <div class="dispenser-head">
                        <span class="d-flex align-items-center gap-2">
                            <i class="bi bi-fuel-pump fs-4"></i>
                            <span class="dispenser-name">{{ $pump->name }}</span>
                        </span>
                        <span class="d-flex align-items-center gap-2">
                            @if ($pump->is_active)
                                <span class="badge text-bg-success">Active</span>
                            @else
                                <span class="badge text-bg-dark">Out of service</span>
                            @endif
                            @if (auth()->user()->isOwner())
                                <a href="{{ route('pumps.edit', $pump) }}" class="btn btn-sm btn-light py-0" aria-label="Manage {{ $pump->name }}"><i class="bi bi-gear"></i></a>
                            @endif
                        </span>
                    </div>

                    @forelse ($pump->nozzles as $nozzle)
                        <div @class(['nozzle-row', \App\Support\Grade::cssClass($nozzle->fuelProduct), 'is-inactive' => ! $nozzle->is_active])>
                            <span class="nozzle-badge">{{ $nozzle->number }}</span>
                            <span class="flex-grow-1" style="min-width: 0;">
                                <span class="fw-semibold d-block">{{ $nozzle->fuelProduct->name }}</span>
                                <span class="small text-secondary">Meter <span class="ft-num">@meter($nozzle->current_meter)</span>{{ $nozzle->is_active ? '' : ', inactive' }}</span>
                            </span>
                            @if ($nozzle->is_active && $pump->is_active)
                                <a href="{{ route('meter-readings.create', ['nozzle' => $nozzle->id]) }}" class="btn btn-sm btn-light text-nowrap">
                                    <i class="bi bi-plus-lg"></i> Reading
                                </a>
                            @endif
                        </div>
                    @empty
                        <div class="empty-state py-4">No nozzles yet.</div>
                    @endforelse
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="card"><div class="empty-state"><i class="bi bi-fuel-pump"></i>No pumps yet.</div></div>
            </div>
        @endforelse
    </div>
</x-layouts.app>
