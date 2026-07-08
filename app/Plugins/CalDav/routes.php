<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : routes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Plugins\CalDav\Http\Controllers\CalDavAdminController;
use Illuminate\Support\Facades\Route;

/**
 * Plugin-eigene Routen (Feature 058): Admin-Panel (Anbindung + manuelles
 * Publish). Admin-Autorisierung wird im Controller geprüft. Kein öffentlicher/
 * unauthentifizierter Endpunkt — CalDAV ist rein ausgehend (Publish).
 */
Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('admin/caldav', [CalDavAdminController::class, 'index'])->name('admin.caldav.index');

    Route::post('admin/caldav/connection', [CalDavAdminController::class, 'store'])->name('admin.caldav.connection.store');
    Route::post('admin/caldav/disconnect', [CalDavAdminController::class, 'disconnect'])->name('admin.caldav.disconnect');

    // Manuelles Publish (auditierter Admin-Vorgang; Scheduler-Äquivalent).
    Route::post('admin/caldav/publish', [CalDavAdminController::class, 'publish'])->name('admin.caldav.publish');
});
