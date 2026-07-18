<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : routes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use App\Plugins\Billbee\Http\Controllers\BillbeeAdminController;
use Illuminate\Support\Facades\Route;

/*
 * Billbee-Admin (MVP-433/434): Bestellspiegel + Sync — Muster JTL-Wawi
 * (Admin-Gate im Controller, Modul-Gating über plans.routes).
 */
Route::middleware(['web', 'auth', \App\Http\Middleware\EnforcePlanModules::class])
    ->prefix('admin/billbee')
    ->name('admin.billbee.')
    ->group(static function (): void {
        Route::get('/', [BillbeeAdminController::class, 'index'])->name('index');
        Route::post('sync', [BillbeeAdminController::class, 'syncNow'])->name('sync');
    });
