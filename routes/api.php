<?php

use App\Http\Controllers\AddonServiceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\PrintPriceController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\StockOpnameController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\QzTrayController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Mayoka POS
|--------------------------------------------------------------------------
*/

// Auth (public)
Route::post('/login', [AuthController::class, 'login']);

// QZ Tray Security Endpoints (Public to allow localhost QZ Tray integration)
Route::get('/qz-tray/cert', [QzTrayController::class, 'getCertificate']);
Route::get('/qz-tray/sign', [QzTrayController::class, 'signRequest']);

// Authenticated routes
Route::middleware(['auth:web', 'branch_scope'])->group(function () {

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    
    // Branches (Owner only)
    Route::middleware('role:owner')->group(function () {
        Route::get('/branches', [BranchController::class, 'index']);
        Route::post('/branches', [BranchController::class, 'store']);
        Route::put('/branches/{branch}', [BranchController::class, 'update']);
        Route::delete('/branches/{branch}', [BranchController::class, 'destroy']);
    });

    // Shifts (all authenticated users)
    Route::get('/shifts/active', [ShiftController::class, 'active']);
    Route::post('/shifts/open', [ShiftController::class, 'open']);
    Route::put('/shifts/{shift}/close', [ShiftController::class, 'close']);
    Route::get('/shifts/{shift}/receipt', [ShiftController::class, 'receipt']);
    Route::post('/shifts/{shift}/print', [ShiftController::class, 'printReport']);

    // Product search & catalog (kasir + owner)
    Route::get('/products/template', [ProductController::class, 'downloadTemplate']);
    Route::post('/products/import', [ProductController::class, 'import']);
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

    // Suppliers
    Route::get('/suppliers/template', [SupplierController::class, 'downloadTemplate']);
    Route::post('/suppliers/import', [SupplierController::class, 'import']);
    Route::get('/suppliers', [SupplierController::class, 'index'])->middleware('permission:purchases.read');
    Route::post('/suppliers', [SupplierController::class, 'store'])->middleware('permission:purchases.create');
    Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->middleware('permission:purchases.update');
    Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])->middleware('permission:purchases.delete');

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

    // Print prices
    Route::get('/print-prices/template', [PrintPriceController::class, 'downloadTemplate']);
    Route::post('/print-prices/import', [PrintPriceController::class, 'import']);
    Route::post('/print-prices', [PrintPriceController::class, 'store'])->middleware('permission:print_prices.create');
    Route::put('/print-prices/{printPrice}', [PrintPriceController::class, 'update'])->middleware('permission:print_prices.update');
    Route::delete('/print-prices/{printPrice}', [PrintPriceController::class, 'destroy'])->middleware('permission:print_prices.delete');

    // Addon services
    Route::get('/addon-services/template', [AddonServiceController::class, 'downloadTemplate']);
    Route::post('/addon-services/import', [AddonServiceController::class, 'import']);
    Route::post('/addon-services', [AddonServiceController::class, 'store'])->middleware('permission:addons.create');
    Route::put('/addon-services/{addonService}', [AddonServiceController::class, 'update'])->middleware('permission:addons.update');
    Route::delete('/addon-services/{addonService}', [AddonServiceController::class, 'destroy'])->middleware('permission:addons.delete');

    // Reports & Dashboard
    Route::middleware('permission:reports.read')->group(function () {
        Route::get('/reports/alerts-count', [ReportController::class, 'alertsCount']);
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
    Route::post('/purchases/{purchase}/void', [PurchaseController::class, 'voidPurchase'])->middleware('permission:purchases.delete');

    // Stock Opname
    Route::get('/stock-opname', [StockOpnameController::class, 'index'])->middleware('permission:purchases.read');
    Route::get('/stock-opname/export', [StockOpnameController::class, 'export'])->middleware('permission:purchases.read');
    Route::post('/stock-opname', [StockOpnameController::class, 'store'])->middleware('permission:purchases.create');
    Route::get('/stock-opname/{stockOpname}', [StockOpnameController::class, 'show'])->middleware('permission:purchases.read');
    Route::put('/stock-opname/{stockOpname}', [StockOpnameController::class, 'update'])->middleware('permission:purchases.update');
    Route::post('/stock-opname/{stockOpname}/complete', [StockOpnameController::class, 'complete'])->middleware('permission:purchases.update');

    // Expenses (Pengeluaran Operasional)
    Route::get('/expenses', [ExpenseController::class, 'index'])->middleware('permission:expenses.read');
    Route::post('/expenses', [ExpenseController::class, 'store'])->middleware('permission:expenses.create');
    Route::put('/expenses/{expense}', [ExpenseController::class, 'update'])->middleware('permission:expenses.update');
    Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])->middleware('permission:expenses.delete');
});
