<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : routes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Plugins\Firefly\Http\Controllers\FireflyAdminController;
use Illuminate\Support\Facades\Route;

// Admin-Recht prüft der Controller; Adresse und Token stehen in den Plugin-Einstellungen.
Route::middleware(['web', 'auth', \App\Http\Middleware\EnforcePlanModules::class])
    ->prefix('admin/firefly')
    ->name('admin.firefly.')
    ->group(static function (): void {
        Route::get('/', [FireflyAdminController::class, 'index'])->name('index');
        Route::post('verify', [FireflyAdminController::class, 'verify'])->name('verify');
        Route::post('sync', [FireflyAdminController::class, 'syncNow'])->name('sync');
        Route::post('disconnect', [FireflyAdminController::class, 'disconnect'])->name('disconnect');
        // Bewegungen ohne Beleg als erledigt markieren (MVP-1628).
        Route::get('bewegungen/{transaction}/erledigen', [FireflyAdminController::class, 'settleForm'])->name('transactions.settle.form');
        Route::post('bewegungen/{transaction}/erledigen', [FireflyAdminController::class, 'settle'])->name('transactions.settle');
        Route::post('bewegungen/{transaction}/wieder-oeffnen', [FireflyAdminController::class, 'reopen'])->name('transactions.reopen');
    });
