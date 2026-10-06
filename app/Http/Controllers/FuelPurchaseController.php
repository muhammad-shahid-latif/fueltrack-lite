<?php

namespace App\Http\Controllers;

use App\Enums\ProductType;
use App\Http\Requests\StoreFuelPurchaseRequest;
use App\Models\FuelProduct;
use App\Models\FuelPurchase;
use App\Services\FuelStockService;
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
                ->with(['fuelProduct', 'recorder'])
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

    public function create(): View
    {
        return view('purchases.create', [
            'products' => FuelProduct::query()
                ->active()
                ->orderByRaw('CASE WHEN type = ? THEN 0 ELSE 1 END', [ProductType::Fuel->value])
                ->orderBy('name')
                ->get(),
            'businessDate' => BusinessClock::today(),
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
        );

        return redirect()
            ->route('purchases.index')
            ->with('success', sprintf(
                'Purchase saved: %s of %s for %s. Stock updated.',
                Format::quantity($purchase->quantity, $product->unit),
                $product->name,
                Format::money($purchase->amount),
            ));
    }
}
