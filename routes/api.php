<?php

use App\Http\Controllers\AddonServiceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\PrintPriceController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CustomerController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Mayoka POS
|--------------------------------------------------------------------------
*/

// Auth (public)
Route::post('/login', [AuthController::class, 'login']);

// Authenticated routes
Route::middleware('auth:web')->group(function () {

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Shifts (all authenticated users)
    Route::get('/shifts/active', [ShiftController::class, 'active']);
    Route::post('/shifts/open', [ShiftController::class, 'open']);
    Route::put('/shifts/{shift}/close', [ShiftController::class, 'close']);
    Route::get('/shifts/{shift}/receipt', [ShiftController::class, 'receipt']);
    Route::post('/shifts/{shift}/print', [ShiftController::class, 'printReport']);

    // Product search & catalog (kasir + owner)
    Route::get('/products/catalog', [ProductController::class, 'catalog']);
    Route::get('/products/search', [ProductController::class, 'search']);

    // Customer search (kasir + owner)
    Route::get('/customers/search', [CustomerController::class, 'search']);

    // Print price calculate (kasir + owner)
    Route::get('/print-prices/calculate', [PrintPriceController::class, 'calculate']);
    Route::get('/print-prices', [PrintPriceController::class, 'index']);

    // Addon services list (kasir + owner)
    Route::get('/addon-services', [AddonServiceController::class, 'index']);

    // Transactions (kasir + owner)
    Route::post('/transactions/checkout', [TransactionController::class, 'checkout']);
    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::get('/transactions/{transaction}', [TransactionController::class, 'show']);
    Route::get('/transactions/{transaction}/receipt', [TransactionController::class, 'receipt']);
    Route::post('/transactions/{transaction}/print', [TransactionController::class, 'print']);
    Route::post('/transactions/{transaction}/return', [App\Http\Controllers\ReturnController::class, 'store']);

    // User management
    Route::get('/users', [UserController::class, 'index'])->middleware('permission:users.read');
    Route::post('/users', [UserController::class, 'store'])->middleware('permission:users.create');
    Route::put('/users/{user}', [UserController::class, 'update'])->middleware('permission:users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->middleware('permission:users.delete');

    // Customer management
    Route::get('/customers', [CustomerController::class, 'index'])->middleware('permission:customers.read');
    Route::post('/customers', [CustomerController::class, 'store'])->middleware('permission:customers.create');
    Route::put('/customers/{customer}', [CustomerController::class, 'update'])->middleware('permission:customers.update');
    Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->middleware('permission:customers.delete');

    // Shift history
    Route::get('/shifts', [ShiftController::class, 'index'])->middleware('permission:reports.read');

    // Categories
    Route::get('/categories', [CategoryController::class, 'index'])->middleware('permission:categories.read');
    Route::post('/categories', [CategoryController::class, 'store'])->middleware('permission:categories.create');
    Route::put('/categories/{category}', [CategoryController::class, 'update'])->middleware('permission:categories.update');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->middleware('permission:categories.delete');

    // Products
    Route::get('/products', [ProductController::class, 'index'])->middleware('permission:products.read');
    Route::post('/products', [ProductController::class, 'store'])->middleware('permission:products.create');
    Route::put('/products/{product}', [ProductController::class, 'update'])->middleware('permission:products.update');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->middleware('permission:products.delete');
    Route::post('/products/{product}/stock-adjust', [ProductController::class, 'stockAdjust'])->middleware('permission:products.update');

    // Print prices & Tiers
    Route::post('/print-prices', [PrintPriceController::class, 'store'])->middleware('permission:print_prices.create');
    Route::put('/print-prices/{printPrice}', [PrintPriceController::class, 'update'])->middleware('permission:print_prices.update');
    Route::delete('/print-prices/{printPrice}', [PrintPriceController::class, 'destroy'])->middleware('permission:print_prices.delete');
    Route::post('/print-prices/{printPrice}/tiers', [PrintPriceController::class, 'storeTier'])->middleware('permission:print_prices.create');
    Route::put('/print-price-tiers/{tier}', [PrintPriceController::class, 'updateTier'])->middleware('permission:print_prices.update');
    Route::delete('/print-price-tiers/{tier}', [PrintPriceController::class, 'destroyTier'])->middleware('permission:print_prices.delete');

    // Addon services
    Route::post('/addon-services', [AddonServiceController::class, 'store'])->middleware('permission:addons.create');
    Route::put('/addon-services/{addonService}', [AddonServiceController::class, 'update'])->middleware('permission:addons.update');
    Route::delete('/addon-services/{addonService}', [AddonServiceController::class, 'destroy'])->middleware('permission:addons.delete');

    // Reports & Dashboard
    Route::middleware('permission:reports.read')->group(function () {
        Route::get('/reports/dashboard', [ReportController::class, 'dashboard']);
        Route::get('/reports/sales', [ReportController::class, 'salesReport']);
        Route::get('/reports/cashier', [ReportController::class, 'cashierReport']);
        Route::get('/reports/shifts', [ReportController::class, 'shiftReport']);
        Route::get('/reports/stock', [ReportController::class, 'stockReport']);
        Route::get('/reports/profit-loss', [ReportController::class, 'profitLoss']);
        Route::get('/reports/cash-flow', [ReportController::class, 'cashFlow']);
        Route::get('/reports/sales/export', [ReportController::class, 'exportSales']);
        Route::get('/reports/cash-flow/export', [ReportController::class, 'exportCashFlow']);
    });

    // Purchases (Pembelian Barang)
    Route::get('/purchases', [PurchaseController::class, 'index'])->middleware('permission:purchases.read');
    Route::get('/purchases/export', [PurchaseController::class, 'exportPurchases'])->middleware('permission:purchases.read');
    Route::post('/purchases', [PurchaseController::class, 'store'])->middleware('permission:purchases.create');
    Route::get('/purchases/{purchase}', [PurchaseController::class, 'show'])->middleware('permission:purchases.read');
    Route::patch('/purchases/{purchase}/mark-paid', [PurchaseController::class, 'markAsPaid'])->middleware('permission:purchases.update');
    Route::delete('/purchases/{purchase}', [PurchaseController::class, 'destroy'])->middleware('permission:purchases.delete');

    // Expenses (Pengeluaran Operasional)
    Route::get('/expenses', [ExpenseController::class, 'index'])->middleware('permission:expenses.read');
    Route::post('/expenses', [ExpenseController::class, 'store'])->middleware('permission:expenses.create');
    Route::put('/expenses/{expense}', [ExpenseController::class, 'update'])->middleware('permission:expenses.update');
    Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])->middleware('permission:expenses.delete');
});
