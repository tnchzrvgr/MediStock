<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FaceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\MedicineController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\RegisterController;

// ---------- login and logout ----------
Route::get('/', function () {
    return redirect()->route('dashboard');
});
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.check');
Route::get('/logout', [AuthController::class, 'logout'])->name('logout');

// step 2 of the login: the face check (the password was already correct)
Route::get('/login/face', [FaceController::class, 'showLoginFace'])->name('login.face');
Route::post('/login/face', [FaceController::class, 'checkLoginFace'])->name('login.face.check');

// ---------- pages for every logged in user ("login" middleware = CheckLogin) ----------
Route::middleware('login')->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // inventory (the staff can look, and record stock-in and adjustment requests)
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory');
    Route::get('/medicines/view/{id}', [MedicineController::class, 'show'])->name('medicines.show');
    Route::get('/stock-in', [StockController::class, 'stockIn'])->name('stock.in');
    Route::post('/stock-in', [StockController::class, 'stockInSave'])->name('stock.in.save');
    Route::get('/stock-adjust', [StockController::class, 'adjust'])->name('stock.adjust');
    Route::post('/stock-adjust', [StockController::class, 'adjustSave'])->name('stock.adjust.save');
    Route::get('/stock-expired', [StockController::class, 'expired'])->name('stock.expired');
    Route::post('/stock-expired', [StockController::class, 'expiredSave'])->name('stock.expired.save');

    // sales
    Route::get('/sales', [SaleController::class, 'index'])->name('sales');
    Route::get('/sales/new', [SaleController::class, 'newSale'])->name('sales.new');
    Route::post('/sales/new', [SaleController::class, 'newSaleAction'])->name('sales.new.action');
    Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions');
    Route::get('/receipt/{id?}', [ReceiptController::class, 'show'])->name('receipt');

    Route::get('/alerts', [AlertController::class, 'index'])->name('alerts');

    // ---------- only the Owner / Admin ("admin" middleware = AdminOnly) ----------
    Route::middleware('admin')->group(function () {
        // add, edit and archive medicines
        Route::get('/medicines/form', [MedicineController::class, 'form'])->name('medicines.form');
        Route::post('/medicines/form', [MedicineController::class, 'save'])->name('medicines.save');

        // approve or reject a stock adjustment request
        Route::post('/stock-adjust/decide', [StockController::class, 'adjustDecide'])->name('stock.adjust.decide');

        // analytics, reports and suppliers
        Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics');
        Route::get('/reports', [ReportController::class, 'index'])->name('reports');
        Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers');
        Route::get('/suppliers/form', [SupplierController::class, 'form'])->name('suppliers.form');
        Route::get('/suppliers/view/{id}', [SupplierController::class, 'show'])->name('suppliers.show');
        Route::post('/suppliers/form', [SupplierController::class, 'save'])->name('suppliers.save');

        Route::get('/users', [UserController::class, 'index'])->name('users');
        Route::post('/users/active', [UserController::class, 'setActive'])->name('users.active');
        Route::get('/users/form', [UserController::class, 'form'])->name('users.form');
        Route::post('/users/form', [UserController::class, 'save'])->name('users.save');
        Route::get('/users/face', [FaceController::class, 'enrollForm'])->name('face.enroll');
        Route::post('/users/face', [FaceController::class, 'enrollSave'])->name('face.enroll.save');
        Route::post('/users/face/remove', [FaceController::class, 'remove'])->name('face.remove');


        // void or return a sale
        Route::post('/transactions/change', [TransactionController::class, 'change'])->name('transactions.change');

        // settings
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
        Route::post('/settings', [SettingsController::class, 'save'])->name('settings.save');
        Route::post('/settings/reset', [SettingsController::class, 'reset'])->name('settings.reset');

        // backup and security
        Route::get('/backup', [BackupController::class, 'index'])->name('backup');
        Route::post('/backup/now', [BackupController::class, 'backupNow'])->name('backup.now');
        Route::post('/backup/retry', [BackupController::class, 'retry'])->name('backup.retry');
        Route::post('/backup/save', [BackupController::class, 'save'])->name('backup.save');
        Route::post('/backup/restore-file', [BackupController::class, 'restoreFile'])->name('backup.restore.file');
        Route::post('/backup/restore', [BackupController::class, 'restoreHistory'])->name('backup.restore');
        Route::get('/backup/download/{id}', [BackupController::class, 'download'])->name('backup.download');
    });
});
Route::middleware(['login', 'admin'])->prefix('users/new')->group(function () {
    Route::get('/', [RegisterController::class, 'start'])->name('register');                    // step 2: choose kind of ID
    Route::post('/consent', [RegisterController::class, 'consent'])->name('register.consent');
    Route::get('/reset', [RegisterController::class, 'reset'])->name('register.reset');

    Route::get('/id', [RegisterController::class, 'idPage'])->name('register.id');              // step 3-4: upload + extraction
    Route::post('/id', [RegisterController::class, 'idSave'])->name('register.id.save');

    Route::get('/face', [RegisterController::class, 'facePage'])->name('register.face');        // step 5: face recognition + matching
    Route::post('/face', [RegisterController::class, 'faceMatch'])->name('register.face.match');

    Route::get('/info', [RegisterController::class, 'infoPage'])->name('register.info');        // step 6: personal information
    Route::post('/info', [RegisterController::class, 'infoSave'])->name('register.info.save');

    Route::get('/done', [RegisterController::class, 'done'])->name('register.done');

});
