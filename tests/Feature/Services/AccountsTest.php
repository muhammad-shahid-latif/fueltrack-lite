<?php

use App\Enums\BillStatus;
use App\Enums\PaymentMethod;
use App\Enums\ProductType;
use App\Enums\ProductUnit;
use App\Models\Customer;
use App\Models\FuelProduct;
use App\Models\Supplier;
use App\Services\CreditService;
use App\Services\DailyClosingService;
use App\Services\FuelStockService;
use App\Services\SaleService;
use App\Services\SupplierService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->suppliers = app(SupplierService::class);
    $this->credits = app(CreditService::class);
    $this->stock = app(FuelStockService::class);
    $this->diesel = FuelProduct::factory()->create(['name' => 'Diesel', 'code' => 'diesel', 'selling_price' => 300]);
});

test('supplier bills are settled oldest first and get a status', function () {
    $this->travelTo(today()->subDays(10));
    $supplier = Supplier::create(['name' => 'Depot', 'payment_terms_days' => 7]);
    $this->stock->recordPurchase($this->diesel, today(), 1000, 280, supplier: $supplier);      // Rs. 280,000 due in 7 days
    $this->travelBack();

    $this->stock->recordPurchase($this->diesel, today(), 500, 280, supplier: $supplier, paidNow: 40000);   // Rs. 140,000
    $this->suppliers->recordPayment($supplier, 200000, PaymentMethod::Bank);

    $account = $this->suppliers->account($supplier);
    $bills = $account['bills']->reverse()->values();   // oldest first

    // 240,000 paid: the first bill gets 240,000 of 280,000 (overdue), the second nothing yet.
    expect($account['balance'])->toBe(180000.0)
        ->and($bills[0]['paid'])->toBe(240000.0)
        ->and($bills[0]['status'])->toBe(BillStatus::Overdue)
        ->and($bills[1]['status'])->toBe(BillStatus::Due)
        ->and($account['overdue'])->toBe(40000.0)
        ->and($account['ledger']->last()['balance'])->toBe(180000.0);
});

test('a supplier cannot be paid more than is owed', function () {
    $supplier = Supplier::create(['name' => 'Depot', 'opening_balance' => 5000]);
    $this->suppliers->recordPayment($supplier, 6000);
})->throws(ValidationException::class, 'Only Rs. 5,000.00 is payable');

test('fuel on credit is valued at the selling price and respects the credit limit', function () {
    $customer = Customer::create(['name' => 'Khan Transport', 'credit_limit' => 50000, 'vehicle_number' => 'TLA-1']);

    $credit = $this->credits->recordCredit($customer, $this->diesel, 100, null);

    expect((float) $credit->amount)->toBe(30000.0)
        ->and($credit->vehicle_number)->toBe('TLA-1')
        ->and($this->credits->balance($customer))->toBe(30000.0);

    $this->credits->recordCredit($customer, $this->diesel, 70, null);
})->throws(ValidationException::class, 'over the credit limit');

test('customer payments reduce the balance and the aging', function () {
    $customer = Customer::create(['name' => 'Mills']);
    $this->credits->recordCredit($customer, null, null, 25000);
    $this->credits->recordPayment($customer, 10000, PaymentMethod::Cash);

    $account = $this->credits->account($customer);

    expect($account['balance'])->toBe(15000.0)
        ->and($account['aging']['0–30 days'])->toBe(15000.0)
        ->and($account['debts'])->toHaveCount(1)
        ->and($account['debts'][0]['status'])->toBe(BillStatus::Partial);

    $this->credits->recordPayment($customer, 20000);
})->throws(ValidationException::class, 'owes only Rs. 15,000.00');

test('a counter item sold on credit lands in the debt register and leaves with its sale', function () {
    $oil = FuelProduct::factory()->create(['name' => 'Engine Oil', 'code' => 'engine-oil', 'type' => ProductType::Lubricant, 'unit' => ProductUnit::Bottle, 'selling_price' => 1800, 'opening_stock' => 10]);
    $customer = Customer::create(['name' => 'Khan Transport']);
    $sales = app(SaleService::class);

    $sale = $sales->recordCounterSale($oil, today(), 2, customer: $customer);
    expect($this->credits->balance($customer))->toBe(3600.0)
        ->and($sale->credit)->not->toBeNull();

    $sales->updateCounterSale($sale, 3, 1800);
    expect($this->credits->balance($customer))->toBe(5400.0);

    $sales->deleteCounterSale($sale);
    expect($this->credits->balance($customer))->toBe(0.0);
});

test('a delivery can be received after the day is closed, but cash cannot leave a closed drawer', function () {
    app(DailyClosingService::class)->close(today(), openingCash: 0, actualCash: 0);
    $supplier = Supplier::create(['name' => 'Depot', 'payment_terms_days' => 7]);

    $purchase = $this->stock->recordPurchase($this->diesel, today(), 1000, 280, supplier: $supplier, paidNow: 100000, paymentMethod: PaymentMethod::Bank);

    expect($purchase->exists)->toBeTrue()
        ->and($this->suppliers->balance($supplier))->toBe(180000.0);

    $this->stock->recordPurchase($this->diesel, today(), 500, 280, supplier: $supplier, paidNow: 1000, paymentMethod: PaymentMethod::Cash);
})->throws(ValidationException::class, 'no cash can leave the drawer');
