<?php

use App\Enums\Shift;
use App\Enums\StockAdjustmentType;
use App\Models\FuelProduct;
use App\Models\Nozzle;
use App\Models\Tank;
use App\Models\User;
use App\Services\DailyClosingService;
use App\Services\FuelStockService;
use App\Services\MeterReadingService;
use App\Services\TankService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tanks = app(TankService::class);
    $this->stock = app(FuelStockService::class);
    $this->product = FuelProduct::factory()->create(['name' => 'Diesel', 'code' => 'diesel', 'opening_stock' => 5000, 'cost_price' => 280, 'selling_price' => 300]);
    $this->tank = Tank::factory()->for($this->product)->create(['capacity' => 20000, 'opening_stock' => 5000]);
    $this->nozzle = Nozzle::factory()->for($this->product)->create(['tank_id' => $this->tank->id]);
});

test('a tank level follows deliveries and nozzle sales', function () {
    $this->stock->recordPurchase($this->product, today(), 8000, 290, tank: $this->tank);
    app(MeterReadingService::class)->record($this->nozzle, today(), Shift::Morning, 0, 1200);

    $row = $this->tanks->levels()->first();

    expect($row['level'])->toBe(11800.0)
        ->and($row['percent'])->toBe(59.0)
        ->and($row['ullage'])->toBe(8200.0)
        ->and($row['received_today'])->toBe(8000.0)
        ->and($row['sold_today'])->toBe(1200.0)
        ->and($row['status'])->toBe('ok');
});

test('a delivery that would overflow the tank is refused', function () {
    $this->stock->recordPurchase($this->product, today(), 15001, 290, tank: $this->tank);
})->throws(ValidationException::class, 'room for only 15,000.00 L');

test('fuel with tanks must name the tank it went into', function () {
    $this->stock->recordPurchase($this->product, today(), 1000, 290);
})->throws(ValidationException::class, 'Choose the tank');

test('each delivery updates the moving weighted average cost', function () {
    // 5,000 L on hand at Rs. 280, plus 5,000 L at Rs. 300 → Rs. 290
    $first = $this->stock->recordPurchase($this->product, today(), 5000, 300, tank: $this->tank);
    // 10,000 L at Rs. 290, plus 10,000 L at Rs. 296 → Rs. 293
    $second = $this->stock->recordPurchase($this->product, today(), 10000, 296, tank: $this->tank);

    expect((float) $first->average_cost)->toBe(290.0)
        ->and((float) $second->average_cost)->toBe(293.0)
        ->and($this->product->fresh()->currentAverageCost())->toBe(293.0);
});

test('a dip corrects the book stock of the tank and the product', function () {
    $owner = User::factory()->create();
    $dip = $this->tanks->recordDip($this->tank, 4950, $owner, 'Evaporation');

    expect($dip->type)->toBe(StockAdjustmentType::Dip)
        ->and((float) $dip->quantity)->toBe(-50.0)
        ->and((float) $dip->book_stock)->toBe(5000.0)
        ->and($this->tanks->level($this->tank))->toBe(4950.0)
        ->and($this->stock->currentStock($this->product, today()))->toBe(4950.0);
});

test('fuel moves between tanks of the same product only', function () {
    $second = Tank::factory()->for($this->product)->create(['capacity' => 10000, 'opening_stock' => 0]);
    $this->tanks->transfer($this->tank, $second, 1500);

    expect($this->tanks->level($this->tank))->toBe(3500.0)
        ->and($this->tanks->level($second))->toBe(1500.0)
        ->and($this->stock->currentStock($this->product, today()))->toBe(5000.0);

    $other = Tank::factory()->for(FuelProduct::factory()->state(['name' => 'Kerosene', 'code' => 'kerosene']))->create();
    $this->tanks->transfer($this->tank, $other, 100);
})->throws(ValidationException::class, 'same product');

test('the ledger lists movements with a running balance', function () {
    $this->stock->recordPurchase($this->product, today(), 2000, 290, tank: $this->tank);
    app(MeterReadingService::class)->record($this->nozzle, today(), Shift::Morning, 0, 500);
    $this->tanks->recordDip($this->tank, 6450);

    $ledger = $this->tanks->ledger($this->tank, today(), today());

    expect($ledger['opening'])->toBe(5000.0)
        ->and($ledger['rows']->pluck('type')->all())->toBe(['delivery', 'sales', 'dip'])
        ->and($ledger['rows']->pluck('balance')->all())->toBe([7000.0, 6500.0, 6450.0])
        ->and($ledger['closing'])->toBe(6450.0);
});

test('fuel can be transferred after the day is closed because no closing figure changes', function () {
    $second = Tank::factory()->for($this->product)->create(['capacity' => 10000, 'opening_stock' => 0]);
    app(DailyClosingService::class)->close(today(), openingCash: 0, actualCash: 0);

    $this->tanks->transfer($this->tank, $second, 500);

    expect($this->tanks->level($this->tank))->toBe(4500.0)
        ->and($this->tanks->level($second))->toBe(500.0)
        ->and($this->stock->currentStock($this->product, today()))->toBe(5000.0);
});

test('dips are still blocked on a closed day because they change stock', function () {
    app(DailyClosingService::class)->close(today(), openingCash: 0, actualCash: 0);

    $this->tanks->recordDip($this->tank, 4900);
})->throws(ValidationException::class, 'already closed');
