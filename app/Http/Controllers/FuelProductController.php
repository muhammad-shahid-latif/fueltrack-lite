<?php

namespace App\Http\Controllers;

use App\Enums\ProductType;
use App\Enums\ProductUnit;
use App\Http\Requests\ChangeFuelPriceRequest;
use App\Http\Requests\StoreFuelProductRequest;
use App\Http\Requests\UpdateFuelProductRequest;
use App\Models\FuelProduct;
use App\Services\FuelPriceService;
use App\Services\FuelStockService;
use App\Support\BusinessClock;
use App\Support\Format;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class FuelProductController extends Controller
{
    public function index(FuelStockService $stock): View
    {
        $products = FuelProduct::query()
            ->with('latestPrice.changer')
            ->withCount('nozzles')
            ->orderByRaw('CASE WHEN type = ? THEN 0 ELSE 1 END', [ProductType::Fuel->value])
            ->orderBy('name')
            ->get();

        return view('fuel-products.index', [
            'fuels' => $products->filter->isMetered()->values(),
            'counterItems' => $products->reject->isMetered()->values(),
            'stock' => $stock->summary(BusinessClock::today())->keyBy(fn (array $row) => $row['product']->id),
        ]);
    }

    public function create(): View
    {
        return view('fuel-products.create', [
            'types' => ProductType::cases(),
            'units' => ProductUnit::cases(),
        ]);
    }

    public function store(StoreFuelProductRequest $request, FuelPriceService $prices): RedirectResponse
    {
        $product = DB::transaction(function () use ($request, $prices) {
            $product = FuelProduct::create([
                'name' => $request->string('name')->trim()->toString(),
                'code' => $this->uniqueCode($request->string('name')->toString()),
                'type' => $request->enum('type', ProductType::class),
                'unit' => $request->enum('unit', ProductUnit::class),
                'selling_price' => $request->input('selling_price'),
                'opening_stock' => $request->input('opening_stock'),
                'low_stock_threshold' => $request->input('low_stock_threshold'),
            ]);

            $prices->record($product, (float) $request->input('selling_price'), BusinessClock::now(), $request->user(), 'Opening price');

            return $product;
        });

        return redirect()
            ->route('fuel-products.edit', $product)
            ->with('success', "{$product->name} added at ".Format::money($product->selling_price).' per '.$product->unit->value.'.');
    }

    public function edit(FuelProduct $fuelProduct): View
    {
        $fuelProduct->load(['prices.changer'])->loadCount(['nozzles', 'sales', 'purchases']);

        return view('fuel-products.edit', [
            'product' => $fuelProduct,
            'units' => $fuelProduct->isMetered() ? [ProductUnit::Liter] : ProductUnit::cases(),
            'canDelete' => $fuelProduct->nozzles_count + $fuelProduct->sales_count + $fuelProduct->purchases_count === 0,
        ]);
    }

    public function update(UpdateFuelProductRequest $request, FuelProduct $fuelProduct): RedirectResponse
    {
        $fuelProduct->update([
            'name' => $request->string('name')->trim()->toString(),
            'unit' => $request->enum('unit', ProductUnit::class),
            'low_stock_threshold' => $request->input('low_stock_threshold'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('fuel-products.edit', $fuelProduct)
            ->with('success', "{$fuelProduct->name} details saved.");
    }

    public function updatePrice(ChangeFuelPriceRequest $request, FuelProduct $fuelProduct, FuelPriceService $prices): RedirectResponse
    {
        $entry = $prices->change($fuelProduct, (float) $request->input('price'), $request->user(), $request->input('note'));

        return redirect()
            ->route('fuel-products.edit', $fuelProduct)
            ->with('success', sprintf(
                '%s price changed to %s from %s.',
                $fuelProduct->name,
                Format::money($entry->price),
                $entry->effective_at->format('d M Y, h:i A'),
            ));
    }

    public function destroy(FuelProduct $fuelProduct): RedirectResponse
    {
        $fuelProduct->loadCount(['nozzles', 'sales', 'purchases']);

        if ($fuelProduct->nozzles_count + $fuelProduct->sales_count + $fuelProduct->purchases_count > 0) {
            return back()->with('danger', "{$fuelProduct->name} has sales, purchases or nozzles, so it cannot be deleted. Mark it inactive instead.");
        }

        $fuelProduct->delete();

        return redirect()->route('fuel-products.index')->with('success', "{$fuelProduct->name} deleted.");
    }

    private function uniqueCode(string $name): string
    {
        $base = Str::slug($name) ?: 'product';
        $code = $base;

        for ($i = 2; FuelProduct::query()->where('code', $code)->exists(); $i++) {
            $code = "{$base}-{$i}";
        }

        return $code;
    }
}
