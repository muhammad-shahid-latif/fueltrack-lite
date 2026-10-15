<x-layouts.app title="Products and prices" subtitle="Fuel sold through nozzles and items sold at the counter">
    @php($canPrice = \App\Support\StaffAccess::can(auth()->user(), 'prices'))
    <div x-data="{ product: null, open(p) { this.product = p; this.$nextTick(() => { const m = document.getElementById('priceModal'); m.addEventListener('shown.bs.modal', () => m.querySelector('#new_price')?.focus(), { once: true }); bootstrap.Modal.getOrCreateInstance(m).show(); }); } }">
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
                            @if (auth()->user()->isOwner() || $canPrice)
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
                                @if (auth()->user()->isOwner() || $canPrice)
                                    <td class="text-end text-nowrap">
                                        @if ($canPrice)
                                            <button type="button" class="btn btn-sm btn-warning"
                                                    @click="open(@js(['name' => $product->name, 'price' => (float) $product->selling_price, 'unit' => $product->unit->value, 'action' => route('fuel-products.price', $product)]))">
                                                <i class="bi bi-tags me-1"></i>Change price
                                            </button>
                                        @endif
                                        @if (auth()->user()->isOwner())
                                            <a href="{{ route('fuel-products.edit', $product) }}" class="btn btn-sm btn-outline-dark" title="Edit product details">
                                                <i class="bi bi-pencil"></i><span class="d-none d-xl-inline ms-1">Edit</span>
                                            </a>
                                        @endif
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

    @if ($canPrice)
        <template x-teleport="body">
            <div class="modal fade khata-modal" id="priceModal" tabindex="-1" aria-labelledby="priceModalTitle" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <form class="modal-content" method="POST" :action="product ? product.action : '#'" novalidate x-data="{ price: '' }" x-effect="product && (price = '')">
                        @csrf
                        @method('PUT')
                        <div class="modal-header">
                            <h2 class="modal-title h5 mb-0" id="priceModalTitle"><i class="bi bi-tags me-2"></i>Change price · <span x-text="product?.name"></span></h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="khata-balance" style="background: #eef4f6; border-color: var(--ft-line); color: var(--ft-ink);">
                                <span>Current price</span>
                                <strong class="ft-num" x-text="product ? 'Rs. ' + product.price.toLocaleString('en-US') + ' / ' + product.unit : ''"></strong>
                            </div>
                            <label for="new_price" class="form-label fw-semibold mt-3">New price (Rs.)</label>
                            <input type="text" inputmode="decimal" id="new_price" name="price" x-model="price" autocomplete="off" class="form-control form-control-lg ft-num" required>
                            <div class="small mt-1" x-show="product && parseFloat(price) > 0">
                                <span x-text="(() => { if (!product) return ''; const d = Math.round((parseFloat(price) - product.price) * 100) / 100; return d === 0 ? 'Same as now' : (d > 0 ? '▲ Rs. ' + d + ' higher' : '▼ Rs. ' + Math.abs(d) + ' lower'); })()"
                                      :class="parseFloat(price) > product?.price ? 'text-danger' : 'text-success'"></span>
                            </div>
                            <input type="text" name="note" maxlength="255" class="form-control mt-3" placeholder="Reason (optional), e.g. OGRA notification">
                            <div class="form-text">Takes effect right away. Earlier sales keep their old rate. The change is logged with your name.</div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-warning px-4" :disabled="!(parseFloat(price) > 0)"><i class="bi bi-check2-circle me-1"></i>Save price</button>
                        </div>
                    </form>
                </div>
            </div>
        </template>
    @endif
    </div>
</x-layouts.app>
