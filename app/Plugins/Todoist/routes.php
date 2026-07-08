<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : routes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Plugins\Todoist\Http\Controllers\{TodoistAdminController, TodoistWebhookController};
use Illuminate\Support\Facades\Route;

/**
 * Plugin-eigene Routen (Feature 055): Admin-Panel + OAuth-Verbindungsflow.
 * Admin-Berechtigung wird im Controller geprüft; der OAuth-Callback läuft in
 * der eingeloggten Sitzung (state ist org- UND sitzungsgebunden).
 */

// Webhook (MVP-115): sessionlos ohne CSRF ('api'-Gruppe) — Autorisierung über
// HMAC-Signatur des Raw-Bodys im Controller, Org-Zuordnung erst danach.
// throttle gegen Flooding des unauthentifizierten Endpunkts (Polling heilt).
Route::middleware(['api', 'throttle:todoist-webhook'])
    ->post('api/webhooks/todoist', TodoistWebhookController::class)
    ->name('api.webhooks.todoist');

Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('admin/todoist', [TodoistAdminController::class, 'index'])->name('admin.todoist.index');

    // OAuth-Verbindung (MVP-111): eine je Organisation.
    Route::post('admin/todoist/oauth/start', [TodoistAdminController::class, 'startOAuth'])->name('admin.todoist.oauth.start');
    Route::get('admin/todoist/oauth/callback', [TodoistAdminController::class, 'oauthCallback'])->name('admin.todoist.oauth.callback');
    Route::post('admin/todoist/disconnect', [TodoistAdminController::class, 'disconnect'])->name('admin.todoist.disconnect');

    // Manueller Vollabgleich (MVP-116): auditierter Admin-Vorgang.
    Route::post('admin/todoist/sync', [TodoistAdminController::class, 'sync'])->name('admin.todoist.sync');

    // Projekt-/Abschnitts-/Benutzerzuordnung + Preflight (MVP-112).
    Route::post('admin/todoist/links', [TodoistAdminController::class, 'storeLink'])->name('admin.todoist.links.store');
    Route::get('admin/todoist/links/{link}/preflight', [TodoistAdminController::class, 'preflight'])->name('admin.todoist.links.preflight');
    Route::post('admin/todoist/links/{link}/status', [TodoistAdminController::class, 'setLinkStatus'])->name('admin.todoist.links.status');
    Route::post('admin/todoist/links/{link}/sections', [TodoistAdminController::class, 'storeSectionLinks'])->name('admin.todoist.links.sections');
    Route::delete('admin/todoist/links/{link}', [TodoistAdminController::class, 'destroyLink'])->name('admin.todoist.links.destroy');
    Route::post('admin/todoist/collaborators', [TodoistAdminController::class, 'assignCollaborator'])->name('admin.todoist.collaborators.assign');
});
