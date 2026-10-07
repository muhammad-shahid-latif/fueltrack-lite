<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Enums\ProductType;
use App\Http\Requests\StoreFuelPurchaseRequest;
use App\Models\FuelProduct;
use App\Models\FuelPurchase;
use App\Models\Supplier;
use App\Models\Tank;
use App\Services\FuelStockService;
use App\Services\TankService;
use App\Support\BusinessClock;
use App\Support\Format;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FuelPurchaseController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = $request->date('from') ?? BusinessClock::today()->subDays(29);
        $to = $request->date('to') ?? BusinessClock::today();

        $scope = fn (Builder $query) => $query
            ->whereDate('business_date', '>=', $from)
            ->whereDate('business_date', '<=', $to);

        return view('purchases.index', [
            'purchases' => FuelPurchase::query()
                ->tap($scope)
                ->with(['fuelProduct', 'recorder', 'tank', 'supplier'])
                ->orderByDesc('business_date')
                ->orderByDesc('id')
                ->paginate(25)
                ->withQueryString(),
            'totals' => FuelPurchase::query()
                ->tap($scope)
                ->join('fuel_products', 'fuel_products.id', '=', 'fuel_purchases.fuel_product_id')
                ->selectRaw('COUNT(*) as deliveries, COALESCE(SUM(fuel_purchases.amount), 0) as amount')
                ->selectRaw('COALESCE(SUM(CASE WHEN fuel_products.type = ? THEN fuel_purchases.quantity ELSE 0 END), 0) as liters', [ProductType::Fuel->value])
                ->toBase()
                ->first(),
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function create(Request $request, FuelStockService $stock, TankService $tanks): View
    {
        $today = BusinessClock::today();

        $products = FuelProduct::query()
            ->active()
            ->orderByRaw('CASE WHEN type = ? THEN 0 ELSE 1 END', [ProductType::Fuel->value])
            ->orderBy('name')
            ->get();

        $stockById = $stock->summary($today)->keyBy(fn (array $row) => $row['product']->id);

        return view('purchases.create', [
            'products' => $products,
            // Live figures for the weighted-average preview on the form.
            'productData' => $products->mapWithKeys(fn (FuelProduct $product) => [$product->id => [
                'unit' => $product->unit->value,
                'stock' => (float) ($stockById->get($product->id)['closing'] ?? 0),
                'average' => $product->currentAverageCost($today),
            ]]),
            'tanks' => $tanks->levels($today, activeOnly: true)->map(fn (array $row) => [
                'id' => $row['tank']->id,
                'name' => $row['tank']->name,
                'product' => $row['tank']->fuel_product_id,
                'level' => $row['level'],
                'ullage' => $row['ullage'],
                'percent' => $row['percent'],
            ])->values(),
            'suppliers' => Supplier::query()->active()->orderBy('name')->get(['id', 'name', 'payment_terms_days']),
            'paymentMethods' => PaymentMethod::cases(),
            'selectedTankId' => $request->integer('tank') ?: null,
            'businessDate' => $today,
        ]);
    }

    public function store(StoreFuelPurchaseRequest $request, FuelStockService $stock): RedirectResponse
    {
        $product = FuelProduct::findOrFail($request->integer('fuel_product_id'));

        $purchase = $stock->recordPurchase(
            product: $product,
            date: BusinessClock::today(),
            quantity: (float) $request->input('quantity'),
            rate: (float) $request->input('rate'),
            supplierName: $request->input('supplier_name'),
            invoiceNumber: $request->input('invoice_number'),
            recordedBy: $request->user(),
            notes: $request->input('notes'),
            tank: $request->filled('tank_id') ? Tank::findOrFail($request->integer('tank_id')) : null,
            supplier: $request->filled('supplier_id') ? Supplier::findOrFail($request->integer('supplier_id')) : null,
            dueDate: $request->input('due_date'),
            paidNow: (float) $request->input('paid_now', 0),
            paymentMethod: $request->enum('payment_method', PaymentMethod::class) ?? PaymentMethod::Cash,
        );

        return redirect()
            ->route('purchases.index')
            ->with('success', sprintf(
                'Purchase saved: %s of %s for %s. Stock updated; average cost now %s per %s.',
                Format::quantity($purchase->quantity, $product->unit),
                $product->name,
                Format::money($purchase->amount),
                Format::money(round((float) $purchase->average_cost, 2)),
                $product->unit->value,
            ));
    }
}
