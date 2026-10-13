<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CreditController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DailyClosingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FuelProductController;
use App\Http\Controllers\FuelPurchaseController;
use App\Http\Controllers\FuelStockController;
use App\Http\Controllers\MeterReadingController;
use App\Http\Controllers\NozzleBoardController;
use App\Http\Controllers\NozzleController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\ProfitLossController;
use App\Http\Controllers\PumpController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SalaryAdvanceController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TankController;
use App\Models\Employee;
use Illuminate\Support\Facades\Route;

// Fuel network theme: picked first, then the login page wears it.
Route::get('/', [BrandController::class, 'home'])->name('home');
Route::get('/brands', [BrandController::class, 'index'])->name('brands.index');
Route::post('/brands', [BrandController::class, 'store'])->name('brands.store');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Operations
    Route::resource('meter-readings', MeterReadingController::class)->only(['index', 'create', 'store']);
    Route::resource('sales', SaleController::class)->only(['index', 'create', 'store']);
    Route::resource('expenses', ExpenseController::class)->only(['index', 'create', 'store']);
    Route::resource('closings', DailyClosingController::class)->only(['index', 'create', 'store', 'show']);

    // Nozzle operations
    Route::get('/nozzles', [NozzleBoardController::class, 'index'])->name('nozzles.index');
    Route::get('/nozzles/live', [NozzleBoardController::class, 'live'])->name('nozzles.live');

    // Debt register: staff record credit sales and receive payments.
    Route::get('/credits', [CreditController::class, 'index'])->name('credits.index');
    Route::get('/credits/create', [CreditController::class, 'create'])->name('credits.create');
    Route::post('/credits', [CreditController::class, 'store'])->name('credits.store');
    Route::post('/customers/{customer}/credits', [CreditController::class, 'storeForCustomer'])->name('customers.credits.store');
    Route::post('/customers/{customer}/payments', [CreditController::class, 'receive'])->name('customers.payments.store');
    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');

    // Tanks: everyone sees levels and takes dips.
    Route::get('/tanks', [TankController::class, 'index'])->name('tanks.index');
    Route::get('/tanks/levels', [TankController::class, 'levels'])->name('tanks.levels');
    Route::post('/tanks/{tank}/dips', [TankController::class, 'dip'])->name('tanks.dips.store');
    Route::get('/stock/movements', StockMovementController::class)->name('stock.movements');

    // Fuel
    Route::get('/fuel-products', [FuelProductController::class, 'index'])->name('fuel-products.index');
    Route::middleware('role:owner')->group(function () {
        Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
        Route::resource('fuel-products', FuelProductController::class)->except(['index', 'show'])->parameters(['fuel-products' => 'fuelProduct']);
        Route::put('/fuel-products/{fuelProduct}/price', [FuelProductController::class, 'updatePrice'])->name('fuel-products.price');

        // Owner corrections. A closed day must be reopened before its records change.
        Route::resource('meter-readings', MeterReadingController::class)->only(['edit', 'update', 'destroy']);
        Route::resource('sales', SaleController::class)->only(['edit', 'update', 'destroy']);
        Route::resource('closings', DailyClosingController::class)->only(['edit', 'update', 'destroy']);

        // Staff and payroll. Every payment is also an expense of the day it is paid.
        Route::resource('employees', EmployeeController::class);
        Route::get('/employees/{employee}/documents/{document}', [EmployeeController::class, 'document'])
            ->whereIn('document', array_keys(Employee::DOCUMENTS))
            ->name('employees.documents');
        Route::post('/employees/{employee}/advances', [SalaryAdvanceController::class, 'store'])->name('employees.advances.store');
        Route::delete('/advances/{advance}', [SalaryAdvanceController::class, 'destroy'])->name('advances.destroy');

        Route::prefix('payroll')->name('payroll.')->controller(PayrollController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/prepare', 'prepare')->name('prepare');
            Route::post('/pay-all', 'payAll')->name('pay-all');
            Route::get('/slips/{slip}', 'show')->name('slips.show');
            Route::get('/slips/{slip}/edit', 'edit')->name('slips.edit');
            Route::put('/slips/{slip}', 'update')->name('slips.update');
            Route::delete('/slips/{slip}', 'destroy')->name('slips.destroy');
            Route::post('/slips/{slip}/pay', 'pay')->name('slips.pay');
            Route::delete('/slips/{slip}/payment', 'reverse')->name('slips.reverse');
        });

        Route::get('/reports/profit-loss', ProfitLossController::class)->name('reports.profit-loss');
        Route::get('/reports/payroll', [PayrollController::class, 'report'])->name('payroll.report');
        Route::get('/activity', ActivityLogController::class)->name('activity.index');

        // Office Expense corrections
        Route::resource('expenses', ExpenseController::class)->only(['edit', 'update', 'destroy']);

        // Tanks
        Route::resource('tanks', TankController::class)->except(['index', 'show']);
        Route::post('/tanks/{tank}/transfer', [TankController::class, 'transfer'])->name('tanks.transfer');

        // Supplier register and payables
        Route::resource('suppliers', SupplierController::class);
        Route::post('/suppliers/{supplier}/payments', [SupplierController::class, 'pay'])->name('suppliers.payments.store');
        Route::delete('/supplier-payments/{payment}', [SupplierController::class, 'destroyPayment'])->name('supplier-payments.destroy');

        // Credit customers: accounts and corrections
        Route::resource('customers', CustomerController::class)->except(['index', 'show']);
        Route::delete('/credits/{credit}', [CreditController::class, 'destroy'])->name('credits.destroy');
        Route::delete('/customer-payments/{payment}', [CreditController::class, 'destroyPayment'])->name('customer-payments.destroy');

        Route::resource('pumps', PumpController::class)->except(['index', 'show']);
        Route::resource('pumps.nozzles', NozzleController::class)->only(['store', 'update', 'destroy']);
    });
    Route::get('/pumps', [PumpController::class, 'index'])->name('pumps.index');
    Route::get('/tanks/{tank}', [TankController::class, 'show'])->name('tanks.show');
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
    Route::get('/stock', FuelStockController::class)->name('stock.index');
    Route::resource('purchases', FuelPurchaseController::class)->only(['index', 'create', 'store']);

    // Reports
    Route::get('/reports/daily', [ReportController::class, 'daily'])->name('reports.daily');
    Route::get('/reports/nozzles', [NozzleBoardController::class, 'performance'])->name('reports.nozzles');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
