@php($editing = $tank->exists)
<x-layouts.app :title="$editing ? 'Edit '.$tank->name : 'Add tank'" subtitle="Underground storage for one fuel">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-6">
            <div class="card">
                <div class="card-body p-4 p-md-5">
                    <form method="POST" action="{{ $editing ? route('tanks.update', $tank) : route('tanks.store') }}" novalidate
                          x-data="{ fuel: @js((int) old('fuel_product_id', $tank->fuel_product_id)), suggested: @js($suggested), opening: @js(old('opening_stock', $editing ? null : '')), submitting: false }"
                          x-init="$watch('fuel', id => { if (! opening && suggested[id]) opening = suggested[id] })"
                          @submit="submitting = true">
                        @csrf
                        @if ($editing) @method('PUT') @endif

                        <div class="row g-3">
                            <div class="col-12">
                                <label for="name" class="form-label">Tank name</label>
                                <input type="text" id="name" name="name" value="{{ old('name', $tank->name) }}" maxlength="50" placeholder="Tank 5 · Diesel"
                                       class="form-control form-control-lg @error('name') is-invalid @enderror" required>
                                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-sm-6">
                                <label for="fuel_product_id" class="form-label">Fuel</label>
                                @if ($editing && $tank->hasHistory())
                                    <input type="text" class="form-control" value="{{ $tank->fuelProduct->name }}" readonly>
                                    <div class="form-text">Fixed: this tank has history.</div>
                                @else
                                    <select id="fuel_product_id" name="fuel_product_id" x-model.number="fuel" class="form-select @error('fuel_product_id') is-invalid @enderror">
                                        <option value="">Choose…</option>
                                        @foreach ($fuels as $fuel)
                                            <option value="{{ $fuel->id }}">{{ $fuel->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('fuel_product_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                @endif
                            </div>

                            <div class="col-sm-6">
                                <label for="capacity" class="form-label">Capacity (L)</label>
                                <input type="text" inputmode="decimal" id="capacity" name="capacity" value="{{ old('capacity', $tank->capacity ? \App\Support\Format::meter($tank->capacity) : '') }}"
                                       class="form-control ft-num @error('capacity') is-invalid @enderror" required>
                                @error('capacity') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            @unless ($editing)
                                <div class="col-12">
                                    <label for="opening_stock" class="form-label">Fuel in the tank now (L)</label>
                                    <input type="text" inputmode="decimal" id="opening_stock" name="opening_stock" x-model="opening"
                                           class="form-control ft-num @error('opening_stock') is-invalid @enderror" required>
                                    @error('opening_stock')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @else
                                        <div class="form-text">Take a dip and enter the measured liters. From here on the level moves with deliveries and nozzle sales.</div>
                                    @enderror
                                </div>
                            @else
                                <div class="col-12">
                                    <div class="form-check form-switch">
                                        <input type="hidden" name="is_active" value="0">
                                        <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" @checked(old('is_active', $tank->is_active))>
                                        <label class="form-check-label" for="is_active">Tank in use</label>
                                    </div>
                                </div>
                            @endunless
                        </div>

                        <div class="d-flex flex-wrap gap-2 justify-content-end mt-4">
                            <a href="{{ $editing ? route('tanks.show', $tank) : route('tanks.index') }}" class="btn btn-light">Cancel</a>
                            <button type="submit" class="btn btn-warning px-4" :disabled="submitting">{{ $editing ? 'Save tank' : 'Add tank' }}</button>
                        </div>
                    </form>

                    @if ($editing && ! $tank->hasHistory())
                        <form method="POST" action="{{ route('tanks.destroy', $tank) }}" class="mt-4 pt-4 border-top text-end" x-data="{ sure: false }">
                            @csrf
                            @method('DELETE')
                            <button type="button" class="btn btn-outline-danger" x-show="! sure" @click="sure = true"><i class="bi bi-trash3 me-1"></i> Delete tank</button>
                            <span x-show="sure" x-cloak><span class="d-inline-flex gap-2">
                                <button type="submit" class="btn btn-danger">Yes, delete</button>
                                <button type="button" class="btn btn-light" @click="sure = false">Keep it</button>
                            </span></span>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
