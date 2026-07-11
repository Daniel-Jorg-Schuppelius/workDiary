<?php
/*
 * Created on   : Fri Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : routes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use App\Plugins\OrgaMax\Http\Controllers\OrgaMaxAdminController;
use Illuminate\Support\Facades\Route;

/*
 * Admin-Routen des orgaMAX-Plugins (Feature 077). Admin-Gate im Controller
 * (isAdmin + Org-Kontext); Faktura-Aktionen verlangen eigene Berechtigungen
 * (finance.orgamax.*). Plan-Gating über module.finance
 * (config/plans.php: admin.orgamax.*). Der iid-Callback läuft im
 * Admin-Browser (angemeldete Session) und verlangt zusätzlich die zuvor
 * begonnene Verbindungsabsicht (State-Token).
 */
Route::middleware(['web', 'auth', \App\Http\Middleware\EnforcePlanModules::class])
    ->prefix('admin/orgamax')
    ->name('admin.orgamax.')
    ->group(function (): void {
        Route::get('/', [OrgaMaxAdminController::class, 'index'])->name('index');
        Route::post('connect', [OrgaMaxAdminController::class, 'startConnect'])->name('connect');
        Route::get('callback', [OrgaMaxAdminController::class, 'callback'])->name('callback');
        Route::post('confirm', [OrgaMaxAdminController::class, 'confirmAccount'])->name('confirm');
        Route::post('capabilities', [OrgaMaxAdminController::class, 'updateCapabilities'])->name('capabilities');
        Route::post('sync', [OrgaMaxAdminController::class, 'syncNow'])->name('sync');
        Route::post('disconnect', [OrgaMaxAdminController::class, 'disconnect'])->name('disconnect');
        // Getrennte, ausdrücklich bestätigte Faktura-Aktionen (MVP-310).
        Route::post('invoices/convert', [OrgaMaxAdminController::class, 'convertOrder'])->name('invoices.convert');
        Route::post('invoices/{externalId}/lock', [OrgaMaxAdminController::class, 'lockInvoice'])->name('invoices.lock');
        Route::post('invoices/{externalId}/send', [OrgaMaxAdminController::class, 'sendInvoice'])->name('invoices.send');
        Route::post('invoices/{externalId}/payment', [OrgaMaxAdminController::class, 'recordPayment'])->name('invoices.payment');
        Route::get('invoices/{externalId}/pdf', [OrgaMaxAdminController::class, 'invoicePdf'])->name('invoices.pdf');
    });
