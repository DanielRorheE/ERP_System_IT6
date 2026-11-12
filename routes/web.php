<?php

use App\Http\Controllers\OwnerDashboardController;
use App\Http\Controllers\BusinessInputController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [OwnerDashboardController::class, 'index'])->name('dashboard');
    Route::get('/business-input', [BusinessInputController::class, 'create'])->name('business-input');
    Route::post('/business-input/sales', [BusinessInputController::class, 'storeSale'])->name('business-input.sales.store');
    Route::post('/business-input/products', [BusinessInputController::class, 'storeProduct'])->name('business-input.products.store');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
