<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : routes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Plugins\Msgraph\Http\Controllers\MsgraphAdminController;
use Illuminate\Support\Facades\Route;

/**
 * Plugin-eigene Routen (MVP-328, Bauturbo A8): Admin-Panel + OAuth-
 * Verbindungsflow. Admin-Berechtigung wird im Controller geprüft; der
 * OAuth-Callback läuft in der eingeloggten Sitzung (state ist org- UND
 * sitzungsgebunden). Kein öffentlicher Endpunkt — rein ausgehendes Publish.
 */
Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('admin/msgraph', [MsgraphAdminController::class, 'index'])->name('admin.msgraph.index');

    // OAuth-Verbindung: eine je Organisation.
    Route::post('admin/msgraph/oauth/start', [MsgraphAdminController::class, 'startOAuth'])->name('admin.msgraph.oauth.start');
    Route::get('admin/msgraph/oauth/callback', [MsgraphAdminController::class, 'oauthCallback'])->name('admin.msgraph.oauth.callback');
    Route::post('admin/msgraph/disconnect', [MsgraphAdminController::class, 'disconnect'])->name('admin.msgraph.disconnect');

    // Ziel-Kalender + manuelles Publish (auditierte Admin-Vorgänge).
    Route::post('admin/msgraph/calendar', [MsgraphAdminController::class, 'selectCalendar'])->name('admin.msgraph.calendar.store');
    Route::post('admin/msgraph/publish', [MsgraphAdminController::class, 'publish'])->name('admin.msgraph.publish');
});
