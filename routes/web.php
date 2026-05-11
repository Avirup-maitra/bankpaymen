<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\BankFileController;
use App\Http\Controllers\BankTransactionController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ExportController;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Bank Files
    Route::get('/bank-files', [BankFileController::class, 'index'])->name('bank-files.index');
    Route::get('/bank-files/summary', [BankFileController::class, 'summary'])->name('bank-files.summary')->can('upload-bank-file');

    // Parameterized route for individual bank files
    Route::get('/bank-files/{bankFile}', [BankFileController::class, 'show'])->name('bank-files.show');

    // Upload - Protected by gate
    Route::get('/bank-files/create/upload', [BankFileController::class, 'create'])->name('bank-files.create')->can('upload-bank-file');
    Route::post('/bank-files', [BankFileController::class, 'store'])->name('bank-files.store')->can('upload-bank-file');

    // Bulk Upload Summary
    Route::get('/bank-files/summary', [BankFileController::class, 'summary'])->name('bank-files.summary')->can('upload-bank-file');

    // Downloads
    Route::get('/bank-files/{bankFile}/rejects', [BankFileController::class, 'downloadRejects'])->name('bank-files.download-rejects');
    Route::get('/bank-files/{bankFile}/error-summary', [BankFileController::class, 'downloadErrorSummary'])->name('bank-files.download-error-summary');
    
    // Transactions
    Route::get('/transactions', [BankTransactionController::class, 'index'])->name('transactions.index');
    Route::get('/transactions/export', [BankTransactionController::class, 'export'])->name('transactions.export');
    Route::get('/transactions/{bankTransaction}', [BankTransactionController::class, 'show'])->name('transactions.show');
    Route::delete('/transactions/{bankTransaction}', [BankTransactionController::class, 'destroy'])->name('transactions.destroy');

    // Exports - Two types: ALL and TODAY
    Route::prefix('/export')->name('export.')->group(function () {
        Route::get('/all', [ExportController::class, 'exportAll'])->name('all');
        Route::get('/today', [ExportController::class, 'exportToday'])->name('today');
        Route::get('/history', [ExportController::class, 'history'])->name('history');
        Route::get('/status/today', [ExportController::class, 'statusToday'])->name('status.today');
        Route::get('/transaction/{id}', [ExportController::class, 'exportTransaction'])->name('transaction');
    });

    // Admin
    Route::middleware(['can:manage-users'])->group(function () {
        Route::get('/admin/users', [AdminController::class, 'users'])->name('admin.users');
        Route::post('/admin/users', [AdminController::class, 'storeUser'])->name('admin.users.store');
        Route::put('/admin/users/{user}', [AdminController::class, 'updateUser'])->name('admin.users.update');
        Route::delete('/admin/users/{user}', [AdminController::class, 'destroyUser'])->name('admin.users.destroy'); // Optional
        
        Route::get('/admin/config', [AdminController::class, 'config'])->name('admin.config');
        Route::post('/admin/config', [AdminController::class, 'updateConfig'])->name('admin.config.update');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// API Routes for bulk uploads
Route::middleware(['auth', 'verified'])->prefix('/api')->group(function () {
    Route::get('/bank-files/bulk-stats', [BankFileController::class, 'bulkUploadStats'])->name('api.bulk-upload-stats');
});

require __DIR__.'/auth.php';
