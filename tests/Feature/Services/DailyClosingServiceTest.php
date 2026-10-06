<?php

use App\Enums\PaymentMethod;
use App\Enums\Shift;
use App\Models\Expense;
use App\Models\FuelProduct;
use App\Models\Nozzle;
use App\Services\DailyClosingService;
use App\Services\MeterReadingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->closings = app(DailyClosingService::class);

    $nozzle = Nozzle::factory()
        ->for(FuelProduct::factory()->state(['selling_price' => 250]))
        ->create();

    // 4,000 L × Rs. 250 = Rs. 1,000,000
    app(MeterReadingService::class)->record($nozzle, today(), Shift::Morning, 0, 4000);

    Expense::factory()->create(['amount' => 30000, 'payment_method' => PaymentMethod::Cash]);
    Expense::factory()->create(['amount' => 15000, 'payment_method' => PaymentMethod::Bank]);
});

test('summary totals sales and separates cash expenses', function () {
    expect($this->closings->summary(today()))->toMatchArray([
        'total_liters' => 4000.0,
        'total_sales' => 1000000.0,
        'total_expenses' => 45000.0,
        'cash_expenses' => 30000.0,
        'is_closed' => false,
    ]);
});

test('closing reconciles cash and records the difference', function () {
    $closing = $this->closings->close(
        date: today(),
        openingCash: 100000,
        actualCash: 718000,
        cardSales: 250000,
        creditSales: 100000,
    );

    // 100,000 + (1,000,000 − 250,000 − 100,000) − 30,000 = 720,000
    expect((float) $closing->cash_sales)->toBe(650000.0)
        ->and((float) $closing->expected_cash)->toBe(720000.0)
        ->and((float) $closing->difference)->toBe(-2000.0)
        ->and($closing->hasCashDifference())->toBeTrue()
        ->and($this->closings->isClosed(today()))->toBeTrue();
});

test('a day can only be closed once', function () {
    $this->closings->close(today(), openingCash: 0, actualCash: 0);
    $this->closings->close(today(), openingCash: 0, actualCash: 0);
})->throws(ValidationException::class, 'already closed');

test('card and credit sales cannot exceed total sales', function () {
    $this->closings->close(today(), openingCash: 0, actualCash: 0, cardSales: 900000, creditSales: 200000);
})->throws(ValidationException::class, 'cannot exceed total sales');
