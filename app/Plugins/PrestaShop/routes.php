<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : routes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Plugins\PrestaShop\Http\Controllers\PrestaShopAdminController;
use Illuminate\Support\Facades\Route;

// Admin-Recht prüft der Controller; Shop-Adresse und Schlüssel stehen in den Plugin-Einstellungen.
Route::middleware(['web', 'auth', \App\Http\Middleware\EnforcePlanModules::class])
    ->prefix('admin/prestashop')
    ->name('admin.prestashop.')
    ->group(static function (): void {
        Route::get('/', [PrestaShopAdminController::class, 'index'])->name('index');
        Route::post('verify', [PrestaShopAdminController::class, 'verify'])->name('verify');
        Route::get('preview', [PrestaShopAdminController::class, 'preview'])->name('preview');
        Route::post('confirm', [PrestaShopAdminController::class, 'confirm'])->name('confirm');
        Route::post('sync', [PrestaShopAdminController::class, 'syncNow'])->name('sync');
        Route::post('disconnect', [PrestaShopAdminController::class, 'disconnect'])->name('disconnect');
    });
