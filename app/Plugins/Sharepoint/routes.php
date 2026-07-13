<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : routes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Plugins\Sharepoint\Http\Controllers\{SharepointAdminController, SharepointConflictController};
use Illuminate\Support\Facades\Route;

/**
 * Plugin-eigene Routen (MVP-330, Bauturbo A10): Admin-Panel + OAuth-
 * Verbindungsflow + Ziel-/Einstellungs-Pflege + manueller Voll-Spiegellauf.
 * Admin-Berechtigung wird im Controller geprüft; der OAuth-Callback läuft in
 * der eingeloggten Sitzung (state ist org- UND sitzungsgebunden). Kein
 * öffentlicher Endpunkt — rein ausgehende Spiegelung.
 */
Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('admin/sharepoint', [SharepointAdminController::class, 'index'])->name('admin.sharepoint.index');

    // OAuth-Verbindung: eine je Organisation.
    Route::post('admin/sharepoint/oauth/start', [SharepointAdminController::class, 'startOAuth'])->name('admin.sharepoint.oauth.start');
    Route::get('admin/sharepoint/oauth/callback', [SharepointAdminController::class, 'oauthCallback'])->name('admin.sharepoint.oauth.callback');
    Route::post('admin/sharepoint/disconnect', [SharepointAdminController::class, 'disconnect'])->name('admin.sharepoint.disconnect');

    // Ziel (Site + Bibliothek) und Ordner-/Quellen-Einstellungen (auditiert).
    Route::post('admin/sharepoint/target', [SharepointAdminController::class, 'selectTarget'])->name('admin.sharepoint.target.store');
    Route::post('admin/sharepoint/settings', [SharepointAdminController::class, 'storeSettings'])->name('admin.sharepoint.settings.store');

    // Manueller Voll-Spiegellauf über alle freigegebenen Dokumente (auditiert).
    Route::post('admin/sharepoint/mirror', [SharepointAdminController::class, 'mirror'])->name('admin.sharepoint.mirror');

    // Konfliktauflösung aus der Zuordnungs-Inbox (Rang-18-Semantik), je Einzel-Item.
    Route::post('admin/sharepoint/conflict/{item}/overwrite', [SharepointConflictController::class, 'overwrite'])->name('admin.sharepoint.conflict.overwrite');
    Route::post('admin/sharepoint/conflict/{item}/import', [SharepointConflictController::class, 'import'])->name('admin.sharepoint.conflict.import');
    Route::post('admin/sharepoint/conflict/{item}/detach', [SharepointConflictController::class, 'detach'])->name('admin.sharepoint.conflict.detach');
});
