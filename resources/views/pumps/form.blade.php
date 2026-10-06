@php($editing = $pump->exists)

<x-layouts.app :title="$editing ? 'Manage '.$pump->name : 'Add pump'" :subtitle="$editing ? 'Rename, take out of service, or manage its nozzles' : 'Nozzles are added on the next step'">
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">Pump</div>
                <div class="card-body p-4">
                    <form method="POST" action="{{ $editing ? route('pumps.update', $pump) : route('pumps.store') }}" novalidate>
                        @csrf
                        @if ($editing)
                            @method('PUT')
                        @endif

                        <div class="mb-3">
                            <label for="name" class="form-label">Name</label>
                            <input type="text" id="name" name="name" value="{{ old('name', $pump->name) }}" maxlength="50"
                                   class="form-control form-control-lg @error('name') is-invalid @enderror" required>
                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        @if ($editing)
                            <div class="form-check form-switch mb-3">
                                <input type="hidden" name="is_active" value="0">
                                <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"
                                       @checked(old('is_active', $pump->is_active))>
                                <label class="form-check-label" for="is_active">In service</label>
                                <div class="form-text">Switch off when the machine is broken or under repair. Its nozzles disappear from the reading form.</div>
                            </div>
                        @endif

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-warning flex-grow-1">{{ $editing ? 'Save pump' : 'Add pump' }}</button>
                            <a href="{{ route('pumps.index') }}" class="btn btn-light">Back</a>
                        </div>
                    </form>

                    @if ($editing)
                        @php($hasReadings = $pump->nozzles->sum('meter_readings_count') > 0)
                        <div class="mt-4 pt-3 border-top">
                            @if ($hasReadings)
                                <p class="small text-secondary mb-0">This pump has readings on record. Switch it out of service instead of deleting it, so its history stays intact.</p>
                            @else
                                <form method="POST" action="{{ route('pumps.destroy', $pump) }}" x-data="{ sure: false }">
                                    @csrf
                                    @method('DELETE')
                                    <button type="button" class="btn btn-sm btn-outline-danger" x-show="! sure" @click="sure = true">Remove pump</button>
                                    <span x-show="sure" x-cloak class="d-flex flex-wrap align-items-center gap-2">
                                        <span class="small">Remove {{ $pump->name }} and its nozzles?</span>
                                        <button type="submit" class="btn btn-sm btn-danger">Yes, remove</button>
                                        <button type="button" class="btn btn-sm btn-light" @click="sure = false">Keep it</button>
                                    </span>
                                </form>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>

        @if ($editing)
            <div class="col-lg-8">
                <div class="card dispenser mb-4">
                    <div class="dispenser-head">
                        <span class="dispenser-name">Nozzles</span>
                        <span class="small text-white-50">{{ $pump->nozzles->count() }} on this pump</span>
                    </div>

                    @forelse ($pump->nozzles as $nozzle)
                        <form method="POST" action="{{ route('pumps.nozzles.update', [$pump, $nozzle]) }}"
                              @class(['nozzle-row flex-wrap', \App\Support\Grade::cssClass($nozzle->fuelProduct), 'is-inactive' => ! $nozzle->is_active])>
                            @csrf
                            @method('PUT')
                            <span class="nozzle-badge">{{ $nozzle->number }}</span>
                            <div style="min-width: 160px;" class="flex-grow-1">
                                <label class="visually-hidden" for="fuel_{{ $nozzle->id }}">Fuel for nozzle {{ $nozzle->number }}</label>
                                <select id="fuel_{{ $nozzle->id }}" name="fuel_product_id" class="form-select form-select-sm">
                                    @foreach ($fuels as $fuel)
                                        <option value="{{ $fuel->id }}" @selected($nozzle->fuel_product_id === $fuel->id)>{{ $fuel->name }}</option>
                                    @endforeach
                                </select>
                                <div class="small text-secondary mt-1">Meter <span class="ft-num">@meter($nozzle->current_meter)</span>, {{ $nozzle->meter_readings_count }} {{ str('reading')->plural($nozzle->meter_readings_count) }}</div>
                            </div>
                            <div class="form-check form-switch mb-0">
                                <input type="hidden" name="is_active" value="0">
                                <input class="form-check-input" type="checkbox" role="switch" id="active_{{ $nozzle->id }}" name="is_active" value="1" @checked($nozzle->is_active)>
                                <label class="form-check-label small" for="active_{{ $nozzle->id }}">Active</label>
                            </div>
                            <button type="submit" class="btn btn-sm btn-dark">Save</button>
                        </form>
                        @if ($nozzle->meter_readings_count === 0)
                            <form method="POST" action="{{ route('pumps.nozzles.destroy', [$pump, $nozzle]) }}" class="px-4 pb-2 text-end" x-data="{ sure: false }">
                                @csrf
                                @method('DELETE')
                                <button type="button" class="btn btn-link btn-sm text-danger p-0" x-show="! sure" @click="sure = true">Remove nozzle {{ $nozzle->number }}</button>
                                <span x-show="sure" x-cloak>
                                    <button type="submit" class="btn btn-sm btn-danger">Yes, remove nozzle {{ $nozzle->number }}</button>
                                    <button type="button" class="btn btn-sm btn-light" @click="sure = false">Keep</button>
                                </span>
                            </form>
                        @endif
                    @empty
                        <div class="empty-state py-4">No nozzles yet. Add the first one below.</div>
                    @endforelse
                </div>

                <div class="card">
                    <div class="card-header">Add a nozzle</div>
                    <div class="card-body p-4">
                        @if ($fuels->isEmpty())
                            <p class="mb-0 text-secondary">Add an active fuel product first.</p>
                        @else
                            <form method="POST" action="{{ route('pumps.nozzles.store', $pump) }}" class="row g-3 align-items-end" novalidate>
                                @csrf
                                <div class="col-sm-5">
                                    <label for="new_fuel" class="form-label">Fuel</label>
                                    <select id="new_fuel" name="fuel_product_id" class="form-select @error('fuel_product_id') is-invalid @enderror">
                                        @foreach ($fuels as $fuel)
                                            <option value="{{ $fuel->id }}" @selected(old('fuel_product_id') == $fuel->id)>{{ $fuel->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('fuel_product_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-sm-4">
                                    <label for="opening_meter" class="form-label">Meter reading now</label>
                                    <input type="text" inputmode="decimal" id="opening_meter" name="opening_meter" value="{{ old('opening_meter') }}"
                                           class="form-control ft-num @error('opening_meter') is-invalid @enderror" placeholder="0.00" required>
                                    @error('opening_meter') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-sm-3">
                                    <button type="submit" class="btn btn-warning w-100">Add nozzle</button>
                                </div>
                                <div class="col-12 form-text mt-1">
                                    Replacing a faulty meter? Mark the old nozzle inactive and add a new one with the new meter's reading.
                                </div>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-layouts.app>
