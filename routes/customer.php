<?php
/*
 * Created on   : Sat May 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : customer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Http\Controllers\CustomerPortal\DashboardController;
use App\Http\Controllers\CustomerPortal\DiaryController;
use App\Http\Controllers\CustomerPortal\InvoiceController;
use App\Http\Controllers\CustomerPortal\LoginController;
use App\Http\Controllers\CustomerPortal\TimeEntryController;
use Illuminate\Support\Facades\Route;

/**
 * Customer-Portal (Rolle `kunde`). Eigener Guard `customer` mit dediziertem
 * Provider (siehe App\Auth\CustomerUserProvider). Interne Routen sind durch
 * die Provider-Trennung technisch nicht erreichbar.
 */
Route::prefix('customer-portal')->name('customer.')->group(function (): void {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::middleware(['auth:customer'])->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::get('/diary', [DiaryController::class, 'index'])->name('diary.index');
        Route::get('/time-entries', [TimeEntryController::class, 'index'])->name('time-entries.index');
        Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    });
});
