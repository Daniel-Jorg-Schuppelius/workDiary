<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : routes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Plugins\Clockify\Http\Controllers\ClockifyController;
use Illuminate\Support\Facades\Route;

/**
 * Plugin-eigene Routen (Clockify-Import). Geladen vom
 * {@see \App\Plugins\Clockify\ClockifyServiceProvider}. Admin-Berechtigung wird
 * im Controller geprüft. Unzugeordnetes wird in der universellen
 * Zuordnungs-Inbox (admin.integration.inbox) aufgelöst.
 */
Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('admin/clockify', [ClockifyController::class, 'index'])->name('admin.clockify.index');
    Route::post('admin/clockify/import-csv', [ClockifyController::class, 'uploadCsv'])->name('admin.clockify.import-csv');
    Route::post('admin/clockify/import-api', [ClockifyController::class, 'importApi'])->name('admin.clockify.import-api');
});
