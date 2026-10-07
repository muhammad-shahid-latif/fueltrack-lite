<?php

use App\Enums\ExpenseCategory;
use App\Models\Employee;
use App\Models\FuelProduct;
use App\Models\User;
use App\Services\DailyClosingService;
use App\Services\ExpenseService;
use App\Services\FuelStockService;
use App\Services\PayrollService;
use App\Services\ProfitLossService;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-10-15 10:00:00');
    $this->withoutVite();

    $this->owner = User::factory()->owner()->create();

    // 10 bottles at cost 1,000, plus 10 bought at 1,200 → average cost 1,100.
    $this->oil = FuelProduct::factory()->lubricant()->create(['opening_stock' => 10, 'cost_price' => 1000, 'selling_price' => 1500]);
    app(FuelStockService::class)->recordPurchase($this->oil, today(), 10, 1200);
    app(SaleService::class)->recordCounterSale($this->oil, today(), 4);

    app(ExpenseService::class)->record(today(), ExpenseCategory::Generator, 'Generator diesel', 300);

    $employee = Employee::factory()->create(['monthly_salary' => 1000]);
    $payroll = app(PayrollService::class);
    $payroll->pay($payroll->prepare('2026-10-01')->first());
});

afterEach(fn () => Carbon::setTestNow());

test('net profit is sales minus cost of goods, expenses and salaries', function () {
    // Expected cash: 6,000 sales − 300 expense − 1,000 salary = 4,700; counted 50 short.
    app(DailyClosingService::class)->close(today(), 0, 4650);

    $report = app(ProfitLossService::class)->report('2026-10-01', '2026-10-15');

    // 4 × 1,500 = 6,000 sales; 4 × 1,100 = 4,400 cost; 1,600 gross.
    expect($report['total_sales'])->toBe(6000.0)
        ->and($report['cost_of_sales'])->toBe(4400.0)
        ->and($report['gross_profit'])->toBe(1600.0)
        ->and($report['total_operating'])->toBe(300.0)
        ->and($report['salaries'])->toBe(1000.0)
        ->and($report['cash_difference'])->toBe(-50.0)
        ->and($report['net_profit'])->toBe(250.0)
        ->and($report['missing_costs'])->toBeEmpty();
});

test('products without any cost are flagged', function () {
    $coolant = FuelProduct::factory()->lubricant()->create(['opening_stock' => 5, 'cost_price' => null]);
    app(SaleService::class)->recordCounterSale($coolant, today(), 1);

    $report = app(ProfitLossService::class)->report(today(), today());

    expect($report['missing_costs']->pluck('id')->all())->toBe([$coolant->id]);
});

test('the owner sees the statement and the month on the dashboard', function () {
    $this->actingAs($this->owner)
        ->get(route('reports.profit-loss'))
        ->assertOk()
        ->assertSee(['Net profit', 'Gross profit', 'Rs. 1,600', 'Cost of goods sold', 'Salaries paid', 'October 2026']);

    $this->actingAs($this->owner)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('October so far: net profit');

    $this->actingAs(User::factory()->staff()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('October so far');
});
