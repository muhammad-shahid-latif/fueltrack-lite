<?php

namespace App\Http\Controllers;

use App\Enums\SalarySlipStatus;
use App\Models\DailyClosing;
use App\Models\FuelProduct;
use App\Models\SalarySlip;
use App\Services\CreditService;
use App\Services\DailyClosingService;
use App\Services\FuelStockService;
use App\Services\NozzleReportService;
use App\Services\ProfitLossService;
use App\Services\ReportService;
use App\Services\SupplierService;
use App\Services\TankService;
use App\Support\Station;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        DailyClosingService $closings,
        FuelStockService $stock,
        ReportService $reports,
        ProfitLossService $profitLoss,
        TankService $tanks,
        CreditService $credits,
        SupplierService $suppliers,
        NozzleReportService $nozzles,
    ): View {
        $today = $closings->summary(today());
        $todayClosing = DailyClosing::query()->whereDate('business_date', today())->first();

        return view('dashboard.index', [
            'today' => $today,
            // Before closing, card/credit split is unknown, so this assumes all sales are cash.
            'cashInHand' => $todayClosing
                ? (float) $todayClosing->actual_cash
                : round(Station::defaultOpeningCash() + $today['total_sales'] - $today['cash_expenses'], 2),
            'todayClosing' => $todayClosing,
            'priceBoard' => FuelProduct::query()->fuel()->active()->with('latestPrice')->orderBy('id')->get(),
            'products' => $reports->dailyReport(today(), today())['products'],
            'stock' => $stock->summary(today()),
            'week' => $reports->days(today()->subDays(6), today()),
            'recentClosings' => DailyClosing::query()->orderByDesc('business_date')->limit(5)->get(),
            'tanks' => $tanks->levels(today(), activeOnly: true),
            'nozzlesToday' => $nozzles->live(today()),
            'receivable' => round($credits->summaries()->sum('balance'), 2),
            // Owner only: what is owed to suppliers and what is overdue.
            'payables' => $request->user()->isOwner() ? (function () use ($suppliers) {
                $rows = $suppliers->summaries();

                return ['balance' => round($rows->sum('balance'), 2), 'overdue' => round($rows->sum('overdue'), 2), 'due_soon' => round($rows->sum('due_soon'), 2)];
            })() : null,
            // Owner only: month-to-date result and salaries still to pay.
            'month' => $request->user()->isOwner() ? [
                'report' => $profitLoss->report(today()->startOfMonth(), today()),
                'pending_salaries' => (float) SalarySlip::query()
                    ->where('status', SalarySlipStatus::Pending->value)
                    ->sum('net_salary'),
            ] : null,
        ]);
    }
}
