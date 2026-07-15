<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : routes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Plugins\Dropbox\Http\Controllers\{DropboxIntakeController, DropboxWebhookController};
use Illuminate\Support\Facades\Route;

/**
 * Plugin-Routen (Feature 080, MVP-353): OAuth-Verbindungsflow in der
 * eingeloggten Sitzung (state org-/nutzergebunden, Rechte im Controller)
 * + öffentlicher, signaturgeprüfter Webhook als reines Aufwecksignal
 * (Mandant serverseitig über gespeicherte Konto-IDs, WebhookTenantTest).
 */
Route::middleware(['web', 'auth'])->group(function (): void {
    Route::post('admin/cloud-intake/dropbox/oauth/start', [DropboxIntakeController::class, 'startOAuth'])->name('admin.cloud-intake.dropbox.oauth.start');
    Route::get('admin/cloud-intake/dropbox/oauth/callback', [DropboxIntakeController::class, 'oauthCallback'])->name('admin.cloud-intake.dropbox.oauth.callback');
});

Route::middleware(['api', 'throttle:webhook-ingest'])->group(function (): void {
    Route::get('api/webhooks/dropbox', [DropboxWebhookController::class, 'verify'])->name('api.webhooks.dropbox.verify');
    Route::post('api/webhooks/dropbox', DropboxWebhookController::class)->name('api.webhooks.dropbox');
});

// ── Cloud-Backupziel (Feature 017 Phase 32, MVP-363) ────────────────────
// Systemweiter OAuth-Flow (Plattform-Admin, Policy im Controller);
// eigene Verbindung + Schreib-Scopes, getrennt vom Dokumenteingang.
Route::middleware(['web', 'auth'])->group(function (): void {
    Route::post('admin/backup-targets/dropbox/oauth/start', [\App\Plugins\Dropbox\Http\Controllers\DropboxBackupTargetController::class, 'startOAuth'])->name('admin.backup-targets.dropbox.oauth.start');
    Route::get('admin/backup-targets/dropbox/oauth/callback', [\App\Plugins\Dropbox\Http\Controllers\DropboxBackupTargetController::class, 'oauthCallback'])->name('admin.backup-targets.dropbox.oauth.callback');
});
