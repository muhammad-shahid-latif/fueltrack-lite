<?php

namespace App\Services;

use App\Enums\ExpenseCategory;
use App\Models\DailyClosing;
use App\Models\Expense;
use App\Models\FuelProduct;
use App\Models\FuelPurchase;
use App\Models\Sale;
use App\Support\BusinessClock;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Profit and loss for any period:
 *
 *   sales − cost of goods sold = gross profit
 *   gross profit − running expenses − salaries ± cash short/excess = net profit
 *
 * Cost of goods sold uses each product's average cost: opening stock at its
 * cost price plus every purchase up to the end of the period. Purchases are
 * stock, not expenses, so only the cost of what was actually sold counts.
 * Salaries and advances count when they are paid (cash basis).
 */
class ProfitLossService
{
    /**
     * @return array{
     *     from: Carbon,
     *     to: Carbon,
     *     products: Collection<int, array{product: FuelProduct, quantity: float, sales: float, unit_cost: ?float, cost: float, gross: float}>,
     *     fuel_sales: float,
     *     counter_sales: float,
     *     total_sales: float,
     *     cost_of_sales: float,
     *     gross_profit: float,
     *     operating_expenses: Collection<int, array{category: ExpenseCategory, amount: float}>,
     *     total_operating: float,
     *     salaries: float,
     *     advances: float,
     *     total_payroll: float,
     *     cash_difference: float,
     *     net_profit: float,
     *     margin: ?float,
     *     missing_costs: Collection<int, FuelProduct>,
     * }
     */
    public function report(DateTimeInterface|string $from, DateTimeInterface|string $to): array
    {
        $from = Carbon::parse($from)->startOfDay();
        $to = Carbon::parse($to)->startOfDay();

        $sold = Sale::query()
            ->whereDate('business_date', '>=', $from)
            ->whereDate('business_date', '<=', $to)
            ->groupBy('fuel_product_id')
            ->selectRaw('fuel_product_id, SUM(quantity) as quantity, SUM(amount) as amount')
            ->toBase()
            ->get()
            ->keyBy('fuel_product_id');

        $products = FuelProduct::query()->whereKey($sold->keys())->orderBy('id')->get();
        $unitCosts = $this->averageCosts($products, $to);

        $rows = $products
            ->map(function (FuelProduct $product) use ($sold, $unitCosts) {
                $quantity = round((float) $sold[$product->id]->quantity, 2);
                $sales = round((float) $sold[$product->id]->amount, 2);
                $unitCost = $unitCosts[$product->id];
                $cost = $unitCost === null ? 0.0 : round($quantity * $unitCost, 2);

                return [
                    'product' => $product,
                    'quantity' => $quantity,
                    'sales' => $sales,
                    'unit_cost' => $unitCost,
                    'cost' => $cost,
                    'gross' => round($sales - $cost, 2),
                ];
            })
            ->sortBy(fn (array $row) => [$row['product']->isMetered() ? 0 : 1, -$row['sales']])
            ->values();

        $expenses = Expense::query()
            ->whereDate('business_date', '>=', $from)
            ->whereDate('business_date', '<=', $to)
            ->groupBy('category')
            ->selectRaw('category, SUM(amount) as amount')
            ->pluck('amount', 'category')
            ->map(fn ($amount) => round((float) $amount, 2));

        $operating = collect(ExpenseCategory::manual())
            ->map(fn (ExpenseCategory $category) => ['category' => $category, 'amount' => (float) ($expenses[$category->value] ?? 0)])
            ->filter(fn (array $row) => $row['amount'] > 0)
            ->sortByDesc('amount')
            ->values();

        $cashDifference = round((float) DailyClosing::query()
            ->whereDate('business_date', '>=', $from)
            ->whereDate('business_date', '<=', $to)
            ->sum('difference'), 2);

        $totalSales = round($rows->sum('sales'), 2);
        $fuelSales = round($rows->filter(fn (array $row) => $row['product']->isMetered())->sum('sales'), 2);
        $costOfSales = round($rows->sum('cost'), 2);
        $grossProfit = round($totalSales - $costOfSales, 2);
        $totalOperating = round($operating->sum('amount'), 2);
        $salaries = (float) ($expenses[ExpenseCategory::Salary->value] ?? 0);
        $advances = (float) ($expenses[ExpenseCategory::StaffAdvance->value] ?? 0);
        $totalPayroll = round($salaries + $advances, 2);
        $netProfit = round($grossProfit - $totalOperating - $totalPayroll + $cashDifference, 2);

        return [
            'from' => $from,
            'to' => $to,
            'products' => $rows,
            'fuel_sales' => $fuelSales,
            'counter_sales' => round($totalSales - $fuelSales, 2),
            'total_sales' => $totalSales,
            'cost_of_sales' => $costOfSales,
            'gross_profit' => $grossProfit,
            'operating_expenses' => $operating,
            'total_operating' => $totalOperating,
            'salaries' => $salaries,
            'advances' => $advances,
            'total_payroll' => $totalPayroll,
            'cash_difference' => $cashDifference,
            'net_profit' => $netProfit,
            'margin' => $totalSales > 0 ? round($netProfit / $totalSales * 100, 1) : null,
            'missing_costs' => $rows->filter(fn (array $row) => $row['unit_cost'] === null)->pluck('product')->values(),
        ];
    }

    /**
     * Month-by-month totals, oldest first. The current month runs to today.
     * Empty months before the station's first activity are left out.
     *
     * @return Collection<int, array{month: Carbon, total_sales: float, gross_profit: float, expenses: float, net_profit: float}>
     */
    public function monthly(int $months = 6, DateTimeInterface|string|null $endMonth = null): Collection
    {
        $end = Carbon::parse($endMonth ?? BusinessClock::today())->startOfMonth();
        $today = BusinessClock::today();

        return collect(range($months - 1, 0))->map(function (int $ago) use ($end, $today) {
            $month = $end->copy()->subMonthsNoOverflow($ago);
            $to = $month->copy()->endOfMonth()->min($today);
            $report = $this->report($month, $to);

            return [
                'month' => $month,
                'total_sales' => $report['total_sales'],
                'gross_profit' => $report['gross_profit'],
                'expenses' => round($report['total_operating'] + $report['total_payroll'], 2),
                'net_profit' => $report['net_profit'],
            ];
        })
            ->skipUntil(fn (array $row) => $row['total_sales'] > 0 || $row['expenses'] > 0)
            ->values();
    }

    /**
     * Weighted average cost per unit as of a date, or null when unknown.
     *
     * @param  Collection<int, FuelProduct>  $products
     * @return array<int, ?float> keyed by product id
     */
    public function averageCosts(Collection $products, DateTimeInterface|string $asOf): array
    {
        $purchases = FuelPurchase::query()
            ->whereIn('fuel_product_id', $products->pluck('id'))
            ->whereDate('business_date', '<=', Carbon::parse($asOf))
            ->groupBy('fuel_product_id')
            ->selectRaw('fuel_product_id, SUM(quantity) as quantity, SUM(amount) as amount')
            ->toBase()
            ->get()
            ->keyBy('fuel_product_id');

        return $products->mapWithKeys(function (FuelProduct $product) use ($purchases) {
            $quantity = (float) ($purchases->get($product->id)->quantity ?? 0);
            $amount = (float) ($purchases->get($product->id)->amount ?? 0);

            if ($product->cost_price !== null) {
                $quantity += (float) $product->opening_stock;
                $amount += (float) $product->opening_stock * (float) $product->cost_price;
            }

            return [$product->id => $quantity > 0 ? round($amount / $quantity, 4) : ($product->cost_price !== null ? (float) $product->cost_price : null)];
        })->all();
    }
}
