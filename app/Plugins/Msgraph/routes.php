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

// ── Kontakt-Push (Feature 102, Schnitt D) ───────────────────────────────
// Fünfter Grant (Contacts.ReadWrite); Push-Button sitzt in der Kundenakte
// (Slot 'customer-show.actions'), die Verbindung im Msgraph-Admin-Panel.
Route::middleware(['web', 'auth'])->group(function (): void {
    Route::post('admin/msgraph/contacts/oauth/start', [\App\Plugins\Msgraph\Http\Controllers\MsgraphContactsController::class, 'startOAuth'])->name('admin.msgraph.contacts.oauth.start');
    Route::get('admin/msgraph/contacts/oauth/callback', [\App\Plugins\Msgraph\Http\Controllers\MsgraphContactsController::class, 'oauthCallback'])->name('admin.msgraph.contacts.oauth.callback');
    Route::post('admin/msgraph/contacts/disconnect', [\App\Plugins\Msgraph\Http\Controllers\MsgraphContactsController::class, 'disconnect'])->name('admin.msgraph.contacts.disconnect');
    Route::post('customers/{customer}/msgraph/contact', [\App\Plugins\Msgraph\Http\Controllers\MsgraphContactsController::class, 'push'])->name('customers.msgraph.contact.push');
});

// ── Free/Busy im Termin-Dialog (Feature 102, C2) ────────────────────────
Route::middleware(['web', 'auth'])
    ->get('msgraph/availability', \App\Plugins\Msgraph\Http\Controllers\MsgraphAvailabilityController::class)
    ->name('msgraph.availability');

// ── To-Do-Sync (Feature 102, Schnitt E) ─────────────────────────────────
// Sechster Grant (Tasks.ReadWrite); Listen-Zuordnungen im Msgraph-Admin-Panel.
Route::middleware(['web', 'auth'])->group(function (): void {
    Route::post('admin/msgraph/tasks/oauth/start', [\App\Plugins\Msgraph\Http\Controllers\MsgraphTasksController::class, 'startOAuth'])->name('admin.msgraph.tasks.oauth.start');
    Route::get('admin/msgraph/tasks/oauth/callback', [\App\Plugins\Msgraph\Http\Controllers\MsgraphTasksController::class, 'oauthCallback'])->name('admin.msgraph.tasks.oauth.callback');
    Route::post('admin/msgraph/tasks/disconnect', [\App\Plugins\Msgraph\Http\Controllers\MsgraphTasksController::class, 'disconnect'])->name('admin.msgraph.tasks.disconnect');
    Route::post('admin/msgraph/tasks/links', [\App\Plugins\Msgraph\Http\Controllers\MsgraphTasksController::class, 'storeLink'])->name('admin.msgraph.tasks.links.store');
    Route::delete('admin/msgraph/tasks/links/{link}', [\App\Plugins\Msgraph\Http\Controllers\MsgraphTasksController::class, 'destroyLink'])->name('admin.msgraph.tasks.links.destroy');
});

// ── Graph-Mail-Versand (Feature 102) ────────────────────────────────────
// Eigener Grant (Mail.Send), getrennt von Kalender/Intake/Backup; die
// Verbindung wird im Msgraph-Admin-Panel verwaltet.
Route::middleware(['web', 'auth'])->group(function (): void {
    Route::post('admin/msgraph/mail/oauth/start', [\App\Plugins\Msgraph\Http\Controllers\MsgraphMailController::class, 'startOAuth'])->name('admin.msgraph.mail.oauth.start');
    Route::get('admin/msgraph/mail/oauth/callback', [\App\Plugins\Msgraph\Http\Controllers\MsgraphMailController::class, 'oauthCallback'])->name('admin.msgraph.mail.oauth.callback');
    Route::post('admin/msgraph/mail/disconnect', [\App\Plugins\Msgraph\Http\Controllers\MsgraphMailController::class, 'disconnect'])->name('admin.msgraph.mail.disconnect');
    Route::post('admin/msgraph/mail/settings', [\App\Plugins\Msgraph\Http\Controllers\MsgraphMailController::class, 'storeSettings'])->name('admin.msgraph.mail.settings');
});

// ── Cloud-Dokumenteingang (Feature 080, MVP-354) ────────────────────────
// Eigener LESENDER Intake-Flow, getrennt von der Kalender-Verbindung.
Route::middleware(['web', 'auth'])->group(function (): void {
    Route::post('admin/cloud-intake/microsoft/oauth/start', [\App\Plugins\Msgraph\Http\Controllers\MsgraphIntakeController::class, 'startOAuth'])->name('admin.cloud-intake.microsoft.oauth.start');
    Route::get('admin/cloud-intake/microsoft/oauth/callback', [\App\Plugins\Msgraph\Http\Controllers\MsgraphIntakeController::class, 'oauthCallback'])->name('admin.cloud-intake.microsoft.oauth.callback');
});

// Graph-Change-Notification: sessionlos ('api'), Validierung + clientState im
// Controller; reines Aufwecksignal (Cursor-Lauf bleibt maßgeblich).
Route::middleware(['api', 'throttle:webhook-ingest'])
    ->post('api/webhooks/msgraph-intake', \App\Plugins\Msgraph\Http\Controllers\MsgraphIntakeWebhookController::class)
    ->name('api.webhooks.msgraph-intake');

// ── Cloud-Backupziel (Feature 017 Phase 32, MVP-363) ────────────────────
// Systemweiter OAuth-Flow (Plattform-Admin, Policy im Controller);
// eigene Verbindung + Schreib-Scopes, getrennt vom Dokumenteingang.
Route::middleware(['web', 'auth'])->group(function (): void {
    Route::post('admin/backup-targets/microsoft/oauth/start', [\App\Plugins\Msgraph\Http\Controllers\MsgraphBackupTargetController::class, 'startOAuth'])->name('admin.backup-targets.microsoft.oauth.start');
    Route::get('admin/backup-targets/microsoft/oauth/callback', [\App\Plugins\Msgraph\Http\Controllers\MsgraphBackupTargetController::class, 'oauthCallback'])->name('admin.backup-targets.microsoft.oauth.callback');
});
