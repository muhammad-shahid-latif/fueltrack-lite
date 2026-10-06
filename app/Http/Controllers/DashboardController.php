<?php

namespace App\Http\Controllers;

use App\Models\DailyClosing;
use App\Models\FuelProduct;
use App\Services\DailyClosingService;
use App\Services\FuelStockService;
use App\Services\ReportService;
use App\Support\Station;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(DailyClosingService $closings, FuelStockService $stock, ReportService $reports): View
    {
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
        ]);
    }
}
