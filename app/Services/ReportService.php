<?php

namespace App\Services;

use App\Enums\ExpenseCategory;
use App\Enums\ProductType;
use App\Models\DailyClosing;
use App\Models\Expense;
use App\Models\FuelProduct;
use App\Models\FuelPurchase;
use App\Models\Sale;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ReportService
{
    /**
     * Everything the daily report shows for a date range, in a fixed number of queries.
     *
     * @return array{
     *     from: Carbon,
     *     to: Carbon,
     *     products: Collection<int, array{product: FuelProduct, quantity: float, amount: float}>,
     *     total_liters: float,
     *     total_sales: float,
     *     fuel_sales: float,
     *     counter_sales: float,
     *     expenses_by_category: Collection<int, array{category: ExpenseCategory, amount: float}>,
     *     total_expenses: float,
     *     net: float,
     *     purchases: array{quantity: float, amount: float},
     *     days: Collection<int, array{date: Carbon, liters: float, sales: float, expenses: float, net: float, closing: ?DailyClosing}>,
     *     cash_difference: float,
     * }
     */
    public function dailyReport(DateTimeInterface|string $from, DateTimeInterface|string $to): array
    {
        $from = Carbon::parse($from)->startOfDay();
        $to = Carbon::parse($to)->startOfDay();

        $salesByProduct = Sale::query()
            ->tap($this->between($from, $to))
            ->groupBy('fuel_product_id')
            ->select('fuel_product_id')
            ->selectRaw('SUM(quantity) as quantity, SUM(amount) as amount')
            ->toBase()
            ->get()
            ->keyBy('fuel_product_id');

        // Every fuel is listed; counter items only when they sold something.
        $products = FuelProduct::query()
            ->orderByRaw('CASE WHEN type = ? THEN 0 ELSE 1 END', [ProductType::Fuel->value])
            ->orderBy('id')
            ->get()
            ->map(fn (FuelProduct $product) => [
                'product' => $product,
                'quantity' => round((float) ($salesByProduct->get($product->id)->quantity ?? 0), 2),
                'amount' => round((float) ($salesByProduct->get($product->id)->amount ?? 0), 2),
            ])
            ->filter(fn (array $row) => $row['product']->isMetered() || $row['amount'] > 0)
            ->values();

        $fuelRows = $products->filter(fn (array $row) => $row['product']->isMetered());

        $expensesByCategory = Expense::query()
            ->tap($this->between($from, $to))
            ->groupBy('category')
            ->select('category')
            ->selectRaw('SUM(amount) as amount')
            ->orderByDesc('amount')
            ->toBase()
            ->get()
            ->map(fn (object $row) => [
                'category' => ExpenseCategory::from($row->category),
                'amount' => round((float) $row->amount, 2),
            ]);

        $purchases = FuelPurchase::query()
            ->tap($this->between($from, $to))
            ->selectRaw('COALESCE(SUM(quantity), 0) as quantity, COALESCE(SUM(amount), 0) as amount')
            ->toBase()
            ->first();

        $days = $this->days($from, $to);
        $totalSales = round($products->sum('amount'), 2);
        $fuelSales = round($fuelRows->sum('amount'), 2);
        $totalExpenses = round($expensesByCategory->sum('amount'), 2);

        return [
            'from' => $from,
            'to' => $to,
            'products' => $products,
            'total_liters' => round($fuelRows->sum('quantity'), 2),
            'total_sales' => $totalSales,
            'fuel_sales' => $fuelSales,
            'counter_sales' => round($totalSales - $fuelSales, 2),
            'expenses_by_category' => $expensesByCategory,
            'total_expenses' => $totalExpenses,
            'net' => round($totalSales - $totalExpenses, 2),
            'purchases' => [
                'quantity' => round((float) $purchases->quantity, 2),
                'amount' => round((float) $purchases->amount, 2),
            ],
            'days' => $days,
            'cash_difference' => round($days->sum(fn (array $day) => (float) ($day['closing']?->difference ?? 0)), 2),
        ];
    }

    /**
     * Per-day sales, liters and expenses, including days with no activity.
     *
     * @return Collection<int, array{date: Carbon, liters: float, sales: float, expenses: float, net: float, closing: ?DailyClosing}>
     */
    public function days(DateTimeInterface|string $from, DateTimeInterface|string $to): Collection
    {
        $from = Carbon::parse($from)->startOfDay();
        $to = Carbon::parse($to)->startOfDay();

        $sales = Sale::query()
            ->join('fuel_products', 'fuel_products.id', '=', 'sales.fuel_product_id')
            ->whereDate('sales.business_date', '>=', $from)
            ->whereDate('sales.business_date', '<=', $to)
            ->selectRaw('DATE(sales.business_date) as day, SUM(sales.amount) as amount')
            ->selectRaw('SUM(CASE WHEN fuel_products.type = ? THEN sales.quantity ELSE 0 END) as liters', [ProductType::Fuel->value])
            ->groupByRaw('DATE(sales.business_date)')
            ->toBase()
            ->get()
            ->keyBy('day');

        $expenses = Expense::query()
            ->tap($this->between($from, $to))
            ->selectRaw('DATE(business_date) as day, SUM(amount) as amount')
            ->groupByRaw('DATE(business_date)')
            ->toBase()
            ->get()
            ->keyBy('day');

        $closings = DailyClosing::query()
            ->tap($this->between($from, $to))
            ->get()
            ->keyBy(fn (DailyClosing $closing) => $closing->business_date->toDateString());

        return collect(range(0, (int) $from->diffInDays($to)))->map(function (int $offset) use ($from, $sales, $expenses, $closings) {
            $date = $from->copy()->addDays($offset);
            $key = $date->toDateString();
            $salesAmount = round((float) ($sales->get($key)->amount ?? 0), 2);
            $expenseAmount = round((float) ($expenses->get($key)->amount ?? 0), 2);

            return [
                'date' => $date,
                'liters' => round((float) ($sales->get($key)->liters ?? 0), 2),
                'sales' => $salesAmount,
                'expenses' => $expenseAmount,
                'net' => round($salesAmount - $expenseAmount, 2),
                'closing' => $closings->get($key),
            ];
        });
    }

    /**
     * @return \Closure(Builder<Model>): Builder<Model>
     */
    private function between(Carbon $from, Carbon $to): \Closure
    {
        return fn (Builder $query) => $query
            ->whereDate('business_date', '>=', $from)
            ->whereDate('business_date', '<=', $to);
    }
}
