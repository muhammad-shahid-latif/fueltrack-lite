<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DailyClosingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FuelProductController;
use App\Http\Controllers\FuelPurchaseController;
use App\Http\Controllers\FuelStockController;
use App\Http\Controllers\MeterReadingController;
use App\Http\Controllers\NozzleController;
use App\Http\Controllers\PumpController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SettingController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::redirect('/', '/dashboard');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Operations
    Route::resource('meter-readings', MeterReadingController::class)->only(['index', 'create', 'store']);
    Route::resource('sales', SaleController::class)->only(['index', 'create', 'store']);
    Route::resource('expenses', ExpenseController::class)->only(['index', 'create', 'store']);
    Route::resource('closings', DailyClosingController::class)->only(['index', 'create', 'store', 'show']);

    // Fuel
    Route::get('/fuel-products', [FuelProductController::class, 'index'])->name('fuel-products.index');
    Route::middleware('role:owner')->group(function () {
        Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
        Route::resource('fuel-products', FuelProductController::class)->except(['index', 'show'])->parameters(['fuel-products' => 'fuelProduct']);
        Route::put('/fuel-products/{fuelProduct}/price', [FuelProductController::class, 'updatePrice'])->name('fuel-products.price');

        Route::resource('pumps', PumpController::class)->except(['index', 'show']);
        Route::resource('pumps.nozzles', NozzleController::class)->only(['store', 'update', 'destroy']);
    });
    Route::get('/pumps', [PumpController::class, 'index'])->name('pumps.index');
    Route::get('/stock', FuelStockController::class)->name('stock.index');
    Route::resource('purchases', FuelPurchaseController::class)->only(['index', 'create', 'store']);

    // Reports
    Route::get('/reports/daily', [ReportController::class, 'daily'])->name('reports.daily');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
