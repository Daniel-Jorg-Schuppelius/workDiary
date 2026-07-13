<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : routes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Plugins\GoogleCalendar\Http\Controllers\GoogleCalendarAdminController;
use Illuminate\Support\Facades\Route;

/**
 * Plugin-eigene Routen (MVP-328, Bauturbo A8): Admin-Panel + OAuth-
 * Verbindungsflow. Admin-Berechtigung wird im Controller geprüft; der
 * OAuth-Callback läuft in der eingeloggten Sitzung (state ist org- UND
 * sitzungsgebunden). Kein öffentlicher Endpunkt — rein ausgehendes Publish.
 */
Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('admin/google-calendar', [GoogleCalendarAdminController::class, 'index'])->name('admin.google-calendar.index');

    // OAuth-Verbindung: eine je Organisation.
    Route::post('admin/google-calendar/oauth/start', [GoogleCalendarAdminController::class, 'startOAuth'])->name('admin.google-calendar.oauth.start');
    Route::get('admin/google-calendar/oauth/callback', [GoogleCalendarAdminController::class, 'oauthCallback'])->name('admin.google-calendar.oauth.callback');
    Route::post('admin/google-calendar/disconnect', [GoogleCalendarAdminController::class, 'disconnect'])->name('admin.google-calendar.disconnect');

    // Ziel-Kalender + manuelles Publish (auditierte Admin-Vorgänge).
    Route::post('admin/google-calendar/calendar', [GoogleCalendarAdminController::class, 'selectCalendar'])->name('admin.google-calendar.calendar.store');
    Route::post('admin/google-calendar/publish', [GoogleCalendarAdminController::class, 'publish'])->name('admin.google-calendar.publish');
});
