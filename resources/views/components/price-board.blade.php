@props(['products'])

<section class="price-board" aria-label="Current fuel prices">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div class="price-board-title"><i class="bi bi-broadcast me-2"></i>Today's fuel prices</div>
        @if (auth()->user()->isOwner())
            <a href="{{ route('fuel-products.index') }}" class="btn btn-sm btn-warning">Change a price</a>
        @endif
    </div>

    <div class="row g-3">
        @foreach ($products as $product)
            <div class="col-sm-6 col-xl">
                <div class="price-cell {{ \App\Support\Grade::cssClass($product) }}">
                    <div class="price-cell-name mb-2"><span class="grade-dot"></span>{{ $product->name }}</div>
                    <div class="price-led">{{ \App\Support\Format::number($product->selling_price) }}<small>Rs/L</small></div>
                    @if ($product->latestPrice)
                        <div class="price-since mt-2">
                            Since {{ $product->latestPrice->effective_at->format('d M, h:i A') }}
                            @if ($product->latestPrice->change())
                                <span @class(['ms-1 fw-semibold', 'text-danger' => $product->latestPrice->change() > 0, 'text-success' => $product->latestPrice->change() < 0])>
                                    {{ $product->latestPrice->change() > 0 ? '▲' : '▼' }} {{ \App\Support\Format::number(abs($product->latestPrice->change())) }}
                                </span>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</section>
