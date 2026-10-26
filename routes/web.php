<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CreditController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DailyClosingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DataResetController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FuelProductController;
use App\Http\Controllers\FuelPurchaseController;
use App\Http\Controllers\FuelStockController;
use App\Http\Controllers\MeterReadingController;
use App\Http\Controllers\NozzleBoardController;
use App\Http\Controllers\NozzleController;
use App\Http\Controllers\OwedFuelController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\ProfitLossController;
use App\Http\Controllers\PumpController;
use App\Http\Controllers\ReliefController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SalaryAdvanceController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\ShiftSettingController;
use App\Http\Controllers\StaffAccessController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TankController;
use App\Http\Controllers\WhatsAppSettingController;
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

// staff.access: modules the owner switched off for the manager (Setup → Manager access).
Route::middleware(['auth', 'staff.access'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Operations
    Route::resource('meter-readings', MeterReadingController::class)->only(['index', 'create', 'store']);
    Route::resource('sales', SaleController::class)->only(['index', 'create', 'store']);
    Route::resource('expenses', ExpenseController::class)->only(['index', 'create', 'store']);
    Route::resource('closings', DailyClosingController::class)->only(['index', 'create', 'store', 'show']);

    // Nozzle operations
    Route::get('/nozzles', [NozzleBoardController::class, 'index'])->name('nozzles.index');
    Route::get('/nozzles/live', [NozzleBoardController::class, 'live'])->name('nozzles.live');

    // Khata register: staff record credit sales and receive payments.
    Route::get('/credits', [CreditController::class, 'index'])->name('credits.index');
    Route::get('/credits/create', [CreditController::class, 'create'])->name('credits.create');
    Route::post('/credits', [CreditController::class, 'store'])->name('credits.store');
    Route::post('/customers/{customer}/credits', [CreditController::class, 'storeForCustomer'])->name('customers.credits.store');
    Route::post('/customers/{customer}/payments', [CreditController::class, 'receive'])->name('customers.payments.store');
    Route::post('/credits/{credit}/owed-fuel', [OwedFuelController::class, 'store'])->name('credits.owed.store');
    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');

    // PM Fuel Relief Scheme: the day's relief total, entered before closing. The owner tracks the claim.
    Route::get('/relief', [ReliefController::class, 'index'])->name('relief.index');
    Route::post('/relief', [ReliefController::class, 'store'])->name('relief.store');
    Route::delete('/relief/{sale}', [ReliefController::class, 'destroy'])->name('relief.destroy');

    // Tanks: everyone sees levels and takes dips.
    Route::get('/tanks', [TankController::class, 'index'])->name('tanks.index');
    Route::get('/tanks/levels', [TankController::class, 'levels'])->name('tanks.levels');
    Route::get('/tanks/{tank}/dips', [TankController::class, 'dips'])->name('tanks.dips.index');
    Route::post('/tanks/{tank}/dips', [TankController::class, 'dip'])->name('tanks.dips.store');
    Route::get('/stock/movements', StockMovementController::class)->name('stock.movements');

    // Fuel
    Route::get('/fuel-products', [FuelProductController::class, 'index'])->name('fuel-products.index');
    // Owner, and the manager unless the owner switched "Change fuel prices" off.
    Route::put('/fuel-products/{fuelProduct}/price', [FuelProductController::class, 'updatePrice'])->name('fuel-products.price');
    Route::middleware('role:owner')->group(function () {
        Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
        Route::post('/settings/reset', DataResetController::class)->middleware('throttle:5,1')->name('settings.reset');
        Route::get('/settings/shifts', [ShiftSettingController::class, 'edit'])->name('shifts.edit');
        Route::put('/settings/shifts', [ShiftSettingController::class, 'update'])->name('shifts.update');
        Route::get('/settings/manager-access', [StaffAccessController::class, 'edit'])->name('access.edit');
        Route::put('/settings/manager-access', [StaffAccessController::class, 'update'])->name('access.update');
        Route::get('/settings/whatsapp', [WhatsAppSettingController::class, 'edit'])->name('whatsapp.edit');
        Route::put('/settings/whatsapp', [WhatsAppSettingController::class, 'update'])->name('whatsapp.update');
        Route::post('/settings/whatsapp/test', [WhatsAppSettingController::class, 'test'])->name('whatsapp.test');
        Route::post('/closings/{closing}/whatsapp', [WhatsAppSettingController::class, 'resend'])->name('closings.whatsapp');
        Route::resource('fuel-products', FuelProductController::class)->except(['index', 'show'])->parameters(['fuel-products' => 'fuelProduct']);

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
        Route::delete('/owed-fuel/{delivery}', [OwedFuelController::class, 'destroy'])->name('owed-fuel.destroy');

        Route::put('/relief/settings', [ReliefController::class, 'settings'])->name('relief.settings');
        Route::post('/relief/claims/{date}', [ReliefController::class, 'receive'])->where('date', '\d{4}-\d{2}-\d{2}')->name('relief.receive');
        Route::delete('/relief/claims/{date}', [ReliefController::class, 'undoReceive'])->where('date', '\d{4}-\d{2}-\d{2}')->name('relief.unreceive');

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
