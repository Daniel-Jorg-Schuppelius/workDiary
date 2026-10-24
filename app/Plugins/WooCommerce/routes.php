<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : routes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Plugins\WooCommerce\Http\Controllers\WooCommerceAdminController;
use Illuminate\Support\Facades\Route;

// Admin-Recht prüft der Controller; Shop-Adresse und Schlüssel stehen in den Plugin-Einstellungen.
Route::middleware(['web', 'auth', \App\Http\Middleware\EnforcePlanModules::class])
    ->prefix('admin/woocommerce')
    ->name('admin.woocommerce.')
    ->group(static function (): void {
        Route::get('/', [WooCommerceAdminController::class, 'index'])->name('index');
        Route::post('verify', [WooCommerceAdminController::class, 'verify'])->name('verify');
        Route::get('preview', [WooCommerceAdminController::class, 'preview'])->name('preview');
        Route::post('confirm', [WooCommerceAdminController::class, 'confirm'])->name('confirm');
        Route::post('sync', [WooCommerceAdminController::class, 'syncNow'])->name('sync');
        Route::post('disconnect', [WooCommerceAdminController::class, 'disconnect'])->name('disconnect');
    });
