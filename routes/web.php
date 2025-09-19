<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\AccountController;
use Illuminate\Support\Facades\DB;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

// Transactions
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/transactions', function () {
        return Inertia::render('Transactions');
    })->name('transactions.index');
    Route::get('/accounts', function () {
        return Inertia::render('Accounts');
    })->name('accounts.index');

    Route::get('/transactions/data', [TransactionController::class, 'data'])->name('transactions.data');
    Route::post('/transactions/import', [TransactionController::class, 'import'])->name('transactions.import');
    Route::get('/transactions/template', [TransactionController::class, 'template'])->name('transactions.template');
    Route::apiResource('transactions', TransactionController::class)->except(['index', 'create', 'edit']);

    // Lightweight endpoint to fetch account types for selects
    Route::get('/account-types', function () {
        return response()->json(DB::table('account_types')->select('id', 'name')->orderBy('name')->get());
    })->name('account-types.index');

    Route::get('/accounts/options', function () {
        return response()->json(DB::table('accounts')->select('id', 'name')->orderBy('name')->get());
    })->name('accounts.options');

    // Accounts CRUD + data
    Route::get('/accounts/data', [AccountController::class, 'data'])->name('accounts.data');
    Route::apiResource('accounts', AccountController::class)->except(['index', 'create', 'edit']);
});
