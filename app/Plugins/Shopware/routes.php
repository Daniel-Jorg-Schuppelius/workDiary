<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : routes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Plugins\Shopware\Http\Controllers\ShopwareAdminController;
use Illuminate\Support\Facades\Route;

// Admin-Recht prüft der Controller; Shop-Adresse und Schlüssel stehen in den Plugin-Einstellungen.
Route::middleware(['web', 'auth', \App\Http\Middleware\EnforcePlanModules::class])
    ->prefix('admin/shopware')
    ->name('admin.shopware.')
    ->group(static function (): void {
        Route::get('/', [ShopwareAdminController::class, 'index'])->name('index');
        Route::post('verify', [ShopwareAdminController::class, 'verify'])->name('verify');
        Route::get('preview', [ShopwareAdminController::class, 'preview'])->name('preview');
        Route::post('confirm', [ShopwareAdminController::class, 'confirm'])->name('confirm');
        Route::post('sync', [ShopwareAdminController::class, 'syncNow'])->name('sync');
        Route::post('disconnect', [ShopwareAdminController::class, 'disconnect'])->name('disconnect');
    });
