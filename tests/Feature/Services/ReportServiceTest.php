<?php

use App\Enums\ExpenseCategory;
use App\Models\DailyClosing;
use App\Models\Expense;
use App\Models\Sale;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed());

test('the report totals agree with the ledger', function () {
    $from = today()->subDays(6);
    $report = app(ReportService::class)->dailyReport($from, today());

    $sales = (float) Sale::query()->whereDate('business_date', '>=', $from)->sum('amount');
    $expenses = (float) Expense::query()->whereDate('business_date', '>=', $from)->sum('amount');

    expect($report['total_sales'])->toBe(round($sales, 2))
        ->and($report['total_expenses'])->toBe(round($expenses, 2))
        ->and($report['net'])->toBe(round($sales - $expenses, 2))
        ->and($report['products']->sum('amount'))->toEqualWithDelta($sales, 0.01)
        ->and($report['days'])->toHaveCount(7)
        ->and($report['days']->sum('sales'))->toEqualWithDelta($sales, 0.01);
});

test('the report includes days without activity', function () {
    $days = app(ReportService::class)->days(today()->addDay(), today()->addDays(2));

    expect($days)->toHaveCount(2)
        ->and($days->sum('sales'))->toBe(0.0)
        ->and($days->first()['closing'])->toBeNull();
});

test('expenses are grouped by category and cash differences are summed', function () {
    $report = app(ReportService::class)->dailyReport(today()->subDays(9), today());

    $expected = (float) DailyClosing::sum('difference');

    expect($report['expenses_by_category']->first()['category'])->toBeInstanceOf(ExpenseCategory::class)
        ->and($report['cash_difference'])->toBe(round($expected, 2));
});
