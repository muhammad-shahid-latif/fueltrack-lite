<x-layouts.app :title="$product->name" :subtitle="$product->type->label().', sold in '.$product->unit->label()">
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card mb-4 {{ \App\Support\Grade::cssClass($product) }}">
                <div class="card-header">Change selling price</div>
                <div class="card-body p-4">
                    <div class="price-board mb-4 p-3">
                        <div class="price-cell-name mb-1"><span class="grade-dot"></span>Current price</div>
                        <div class="price-led">{{ \App\Support\Format::number($product->selling_price) }}<small>Rs/{{ $product->unit->value }}</small></div>
                        @if ($product->prices->first())
                            <div class="price-since mt-2">Since {{ $product->prices->first()->effective_at->format('d M Y, h:i A') }}</div>
                        @endif
                    </div>

                    <form method="POST" action="{{ route('fuel-products.price', $product) }}" novalidate>
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="price" class="form-label">New price (Rs. per {{ $product->unit->value }})</label>
                            <input type="text" inputmode="decimal" id="price" name="price" value="{{ old('price') }}"
                                   class="form-control form-control-lg ft-num @error('price') is-invalid @enderror" required>
                            @error('price') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3">
                            <label for="note" class="form-label">Reason <span class="text-secondary fw-normal small">(optional)</span></label>
                            <input type="text" id="note" name="note" value="{{ old('note') }}" maxlength="255"
                                   class="form-control @error('note') is-invalid @enderror" placeholder="e.g. OGRA notification">
                            @error('note') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <p class="small text-secondary">Takes effect immediately, stamped with the server date and time. Earlier sales keep their old rate.</p>
                        <button type="submit" class="btn btn-warning w-100">Change price</button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Details</div>
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('fuel-products.update', $product) }}" novalidate>
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="name" class="form-label">Name</label>
                            <input type="text" id="name" name="name" value="{{ old('name', $product->name) }}" maxlength="50"
                                   class="form-control @error('name') is-invalid @enderror" required>
                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-sm-4">
                                <label for="unit" class="form-label">Sold in</label>
                                <select id="unit" name="unit" class="form-select @error('unit') is-invalid @enderror">
                                    @foreach ($units as $unit)
                                        <option value="{{ $unit->value }}" @selected(old('unit', $product->unit->value) === $unit->value)>{{ $unit->label() }}</option>
                                    @endforeach
                                </select>
                                @error('unit') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-sm-4">
                                <label for="cost_price" class="form-label">Cost price (Rs.)</label>
                                <input type="text" inputmode="decimal" id="cost_price" name="cost_price"
                                       value="{{ old('cost_price', $product->cost_price !== null ? \App\Support\Format::number($product->cost_price) : '') }}"
                                       class="form-control ft-num @error('cost_price') is-invalid @enderror" placeholder="Purchase rate">
                                @error('cost_price')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @else
                                    <div class="form-text">Of the opening stock, for profit &amp; loss</div>
                                @enderror
                            </div>
                            <div class="col-sm-4">
                                <label for="low_stock_threshold" class="form-label">Reorder level</label>
                                <input type="text" inputmode="decimal" id="low_stock_threshold" name="low_stock_threshold"
                                       value="{{ old('low_stock_threshold', \App\Support\Format::number($product->low_stock_threshold)) }}"
                                       class="form-control ft-num @error('low_stock_threshold') is-invalid @enderror" required>
                                @error('low_stock_threshold') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="form-check form-switch mb-4">
                            <input type="hidden" name="is_active" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"
                                   @checked(old('is_active', $product->is_active))>
                            <label class="form-check-label" for="is_active">Active (shown in sales and purchase forms)</label>
                        </div>

                        <div class="d-flex flex-wrap gap-2 justify-content-between">
                            <button type="submit" class="btn btn-dark">Save details</button>
                            <a href="{{ route('fuel-products.index') }}" class="btn btn-light">Back to products</a>
                        </div>
                    </form>

                    @if ($canDelete)
                        <form method="POST" action="{{ route('fuel-products.destroy', $product) }}" class="mt-4 pt-3 border-top" x-data="{ sure: false }">
                            @csrf
                            @method('DELETE')
                            <button type="button" class="btn btn-sm btn-outline-danger" x-show="! sure" @click="sure = true">Delete product</button>
                            <span x-show="sure" x-cloak><span class="d-flex flex-wrap align-items-center gap-2">
                                <span class="small">Delete {{ $product->name }} permanently?</span>
                                <button type="submit" class="btn btn-sm btn-danger">Yes, delete</button>
                                <button type="button" class="btn btn-sm btn-light" @click="sure = false">Keep it</button>
                            </span></span>
                        </form>
                    @else
                        <p class="small text-secondary mt-4 pt-3 border-top mb-0">
                            This product has sales, purchases or nozzles on record, so it can only be made inactive, not deleted.
                        </p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header">Price history</div>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Effective from</th>
                                <th class="text-end">Price</th>
                                <th class="text-end">Change</th>
                                <th>Changed by</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($product->prices as $entry)
                                <tr @class(['table-active' => $loop->first])>
                                    <td class="text-nowrap">
                                        <span class="fw-semibold d-block">{{ $entry->effective_at->format('d M Y') }}</span>
                                        <span class="small text-secondary">{{ $entry->effective_at->format('h:i A') }}</span>
                                    </td>
                                    <td class="text-end ft-num fw-bold">@money($entry->price)</td>
                                    <td class="text-end ft-num">
                                        @if ($entry->change())
                                            <span @class(['text-danger' => $entry->change() > 0, 'text-success' => $entry->change() < 0])>
                                                {{ $entry->change() > 0 ? '+' : '−' }}{{ \App\Support\Format::number(abs($entry->change())) }}
                                            </span>
                                        @else
                                            <span class="text-secondary">—</span>
                                        @endif
                                    </td>
                                    <td class="small">
                                        {{ $entry->changer?->name ?? '—' }}
                                        @if ($entry->note)
                                            <div class="text-secondary">{{ $entry->note }}</div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="empty-state">No price changes recorded yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
