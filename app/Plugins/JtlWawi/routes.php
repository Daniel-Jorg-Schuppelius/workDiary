<?php
/*
 * Created on   : Sat Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : routes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use App\Plugins\JtlWawi\Http\Controllers\JtlAdminController;
use Illuminate\Support\Facades\Route;

/*
 * Admin-Routen des JTL-Wawi-Plugins (Feature 078). Admin-Gate im Controller
 * (isAdmin + Org-Kontext, Muster Todoist/Zammad); der Moduswechsel verlangt
 * zusätzlich `inventory.configure`. Plan-Gating über `module.lager`
 * (config/plans.php: admin.jtl.*).
 */
Route::middleware(['web', 'auth', \App\Http\Middleware\EnforcePlanModules::class])
    ->prefix('admin/jtl')
    ->name('admin.jtl.')
    ->group(function (): void {
        Route::get('/', [JtlAdminController::class, 'index'])->name('index');
        Route::post('connection', [JtlAdminController::class, 'storeConnection'])->name('connection.store');
        Route::post('connection/register', [JtlAdminController::class, 'startRegistration'])->name('connection.register');
        Route::post('connection/check', [JtlAdminController::class, 'checkRegistration'])->name('connection.check');
        Route::post('connection/disconnect', [JtlAdminController::class, 'disconnect'])->name('connection.disconnect');
        Route::post('sync', [JtlAdminController::class, 'syncNow'])->name('sync');
        Route::post('warehouses/{mapping}/map', [JtlAdminController::class, 'mapWarehouse'])->name('warehouses.map');
        Route::post('mode', [JtlAdminController::class, 'updateMode'])->name('mode.update');
        Route::post('takeover', [JtlAdminController::class, 'takeover'])->name('takeover');
    });
