<x-layouts.app title="Add product" subtitle="Fuel for nozzles, or an item for counter sales">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-6">
            <div class="card">
                <div class="card-body p-4 p-md-5">
                    <form method="POST" action="{{ route('fuel-products.store') }}" novalidate x-data="{ type: @js(old('type', 'lubricant')) }">
                        @csrf

                        <div class="mb-3">
                            <label for="name" class="form-label">Name</label>
                            <input type="text" id="name" name="name" value="{{ old('name') }}" maxlength="50"
                                   class="form-control form-control-lg @error('name') is-invalid @enderror" placeholder="e.g. Engine Oil 1L" required>
                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <label for="type" class="form-label">Type</label>
                                <select id="type" name="type" x-model="type" class="form-select @error('type') is-invalid @enderror">
                                    @foreach ($types as $type)
                                        <option value="{{ $type->value }}" @selected(old('type', 'lubricant') === $type->value)>{{ $type->label() }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text" x-show="type === 'fuel'">Fuel is sold through nozzle meter readings.</div>
                                <div class="form-text" x-show="type !== 'fuel'" x-cloak>Sold through counter sales.</div>
                                @error('type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-sm-6">
                                <label for="unit" class="form-label">Sold in</label>
                                <select id="unit" name="unit" class="form-select @error('unit') is-invalid @enderror" :disabled="type === 'fuel'">
                                    @foreach ($units as $unit)
                                        <option value="{{ $unit->value }}" @selected(old('unit', 'bottle') === $unit->value)>{{ $unit->label() }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text" x-show="type === 'fuel'">Fuel is always in liters.</div>
                                @error('unit') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-sm-4">
                                <label for="selling_price" class="form-label">Selling price (Rs.)</label>
                                <input type="text" inputmode="decimal" id="selling_price" name="selling_price" value="{{ old('selling_price') }}"
                                       class="form-control ft-num @error('selling_price') is-invalid @enderror" required>
                                @error('selling_price') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-sm-4">
                                <label for="opening_stock" class="form-label">Stock now</label>
                                <input type="text" inputmode="decimal" id="opening_stock" name="opening_stock" value="{{ old('opening_stock', 0) }}"
                                       class="form-control ft-num @error('opening_stock') is-invalid @enderror" required>
                                @error('opening_stock') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-sm-4">
                                <label for="low_stock_threshold" class="form-label">Reorder level</label>
                                <input type="text" inputmode="decimal" id="low_stock_threshold" name="low_stock_threshold" value="{{ old('low_stock_threshold', 0) }}"
                                       class="form-control ft-num @error('low_stock_threshold') is-invalid @enderror" required>
                                @error('low_stock_threshold') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="d-flex gap-2 justify-content-end">
                            <a href="{{ route('fuel-products.index') }}" class="btn btn-light">Cancel</a>
                            <button type="submit" class="btn btn-warning px-4">Add product</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
