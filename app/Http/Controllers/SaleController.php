<?php

namespace App\Http\Controllers;

use App\Enums\ProductType;
use App\Http\Requests\StoreCounterSaleRequest;
use App\Models\FuelProduct;
use App\Models\Sale;
use App\Services\FuelStockService;
use App\Services\SaleService;
use App\Support\BusinessClock;
use App\Support\Format;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SaleController extends Controller
{
    /**
     * All sales by default; date, product and source filters are optional.
     */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'product' => ['nullable', 'integer', 'exists:fuel_products,id'],
            'source' => ['nullable', 'in:meter,counter'],
        ]);

        $from = $request->date('from');
        $to = $request->date('to');

        $scope = fn (Builder $query) => $query
            ->when($from, fn (Builder $query) => $query->whereDate('sales.business_date', '>=', $from))
            ->when($to, fn (Builder $query) => $query->whereDate('sales.business_date', '<=', $to))
            ->when($filters['product'] ?? null, fn (Builder $query, int $id) => $query->where('sales.fuel_product_id', $id))
            ->when(($filters['source'] ?? null) === 'meter', fn (Builder $query) => $query->whereNotNull('sales.meter_reading_id'))
            ->when(($filters['source'] ?? null) === 'counter', fn (Builder $query) => $query->whereNull('sales.meter_reading_id'));

        $totals = Sale::query()
            ->tap($scope)
            ->join('fuel_products', 'fuel_products.id', '=', 'sales.fuel_product_id')
            ->selectRaw('COUNT(*) as entries, COALESCE(SUM(sales.amount), 0) as amount')
            ->selectRaw('COALESCE(SUM(CASE WHEN fuel_products.type = ? THEN sales.amount ELSE 0 END), 0) as fuel_amount', [ProductType::Fuel->value])
            ->selectRaw('COALESCE(SUM(CASE WHEN fuel_products.type = ? THEN sales.quantity ELSE 0 END), 0) as liters', [ProductType::Fuel->value])
            ->toBase()
            ->first();

        return view('sales.index', [
            'sales' => Sale::query()
                ->tap($scope)
                ->with(['fuelProduct', 'meterReading.nozzle.pump', 'recorder'])
                ->orderByDesc('business_date')
                ->orderByDesc('id')
                ->paginate(30)
                ->withQueryString(),
            'totals' => $totals,
            'products' => FuelProduct::query()->orderByRaw('CASE WHEN type = ? THEN 0 ELSE 1 END', [ProductType::Fuel->value])->orderBy('name')->get(),
            'filters' => [
                'from' => $from,
                'to' => $to,
                'product' => $filters['product'] ?? null,
                'source' => $filters['source'] ?? null,
            ],
            'isFiltered' => array_filter($filters) !== [],
        ]);
    }

    public function create(FuelStockService $stock): View
    {
        $items = FuelProduct::query()->counterItems()->active()->orderBy('name')->get();
        $stockById = $items->isEmpty()
            ? collect()
            : $stock->summary(BusinessClock::today())->keyBy(fn (array $row) => $row['product']->id);

        return view('sales.create', [
            'items' => $items->map(fn (FuelProduct $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'unit' => $item->unit->value,
                'rate' => (float) $item->selling_price,
                'stock' => (float) ($stockById->get($item->id)['closing'] ?? 0),
            ])->values(),
            'businessDate' => BusinessClock::today(),
        ]);
    }

    public function store(StoreCounterSaleRequest $request, SaleService $sales): RedirectResponse
    {
        $sale = $sales->recordCounterSale(
            product: FuelProduct::findOrFail($request->integer('fuel_product_id')),
            date: BusinessClock::today(),
            quantity: (float) $request->input('quantity'),
            recordedBy: $request->user(),
            notes: $request->input('notes'),
        );

        return redirect()
            ->route('sales.index', ['source' => 'counter'])
            ->with('success', sprintf(
                'Counter sale saved: %s × %s = %s',
                Format::quantity($sale->quantity, $sale->fuelProduct->unit),
                Format::money($sale->rate),
                Format::money($sale->amount),
            ));
    }
}
