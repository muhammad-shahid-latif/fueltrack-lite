<x-layouts.app title="Products and prices" subtitle="Fuel sold through nozzles and items sold at the counter">
    @if (auth()->user()->isOwner())
        <div class="d-flex justify-content-end mb-3">
            <a href="{{ route('fuel-products.create') }}" class="btn btn-warning"><i class="bi bi-plus-lg me-1"></i> Add product</a>
        </div>
    @endif

    @foreach (['Fuel' => $fuels, 'Counter items' => $counterItems] as $heading => $group)
        <div class="card mb-4">
            <div class="card-header">{{ $heading }}</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th class="text-end">Selling price</th>
                            <th>Price since</th>
                            <th class="text-end">In stock</th>
                            <th class="text-end">Reorder level</th>
                            <th>Status</th>
                            @if (auth()->user()->isOwner())
                                <th></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($group as $product)
                            @php($row = $stock->get($product->id))
                            <tr>
                                <td>
                                    <span class="d-flex align-items-center gap-2 {{ \App\Support\Grade::cssClass($product) }}">
                                        <span class="grade-dot"></span>
                                        <span>
                                            <span class="fw-semibold d-block">{{ $product->name }}</span>
                                            <span class="small text-secondary">
                                                {{ $product->type->label() }}@if ($product->isMetered()), {{ $product->nozzles_count }} {{ str('nozzle')->plural($product->nozzles_count) }}@endif
                                            </span>
                                        </span>
                                    </span>
                                </td>
                                <td class="text-end ft-num fw-bold">@money($product->selling_price) <span class="text-secondary fw-normal small">/ {{ $product->unit->value }}</span></td>
                                <td class="small text-secondary text-nowrap">
                                    @if ($product->latestPrice)
                                        {{ $product->latestPrice->effective_at->format('d M Y, h:i A') }}
                                        @if ($product->latestPrice->changer)
                                            <br>by {{ $product->latestPrice->changer->name }}
                                        @endif
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-end ft-num">
                                    @if ($row)
                                        <span @class(['text-danger fw-semibold' => $row['is_low']])>@qty($row['closing'], $product->unit)</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-end ft-num">@qty($product->low_stock_threshold, $product->unit)</td>
                                <td>
                                    @if ($product->is_active)
                                        <span class="badge text-bg-success">Active</span>
                                    @else
                                        <span class="badge text-bg-secondary">Inactive</span>
                                    @endif
                                </td>
                                @if (auth()->user()->isOwner())
                                    <td class="text-end text-nowrap">
                                        <a href="{{ route('fuel-products.edit', $product) }}" class="btn btn-sm btn-outline-dark">
                                            <i class="bi bi-pencil me-1"></i>Edit or change price
                                        </a>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="7" class="empty-state">Nothing here yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach

    <p class="small text-secondary">
        A new price applies from the moment it is saved. Earlier sales keep the rate they were recorded at.
    </p>
</x-layouts.app>
