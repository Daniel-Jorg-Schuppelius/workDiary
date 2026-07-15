<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : routes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Plugins\GoogleDrive\Http\Controllers\{GoogleDriveIntakeController, GoogleDriveWebhookController};
use Illuminate\Support\Facades\Route;

/**
 * Plugin-Routen (Feature 080, MVP-355): OAuth-Verbindungsflow in der
 * eingeloggten Sitzung + Watch-Channel-Endpunkt als reines Aufwecksignal
 * (Channel-ID+Token, WebhookTenantTest).
 */
Route::middleware(['web', 'auth'])->group(function (): void {
    Route::post('admin/cloud-intake/google/oauth/start', [GoogleDriveIntakeController::class, 'startOAuth'])->name('admin.cloud-intake.google.oauth.start');
    Route::get('admin/cloud-intake/google/oauth/callback', [GoogleDriveIntakeController::class, 'oauthCallback'])->name('admin.cloud-intake.google.oauth.callback');
});

Route::middleware(['api', 'throttle:webhook-ingest'])
    ->post('api/webhooks/google-drive', GoogleDriveWebhookController::class)
    ->name('api.webhooks.google-drive');

// ── Cloud-Backupziel (Feature 017 Phase 32, MVP-363) ────────────────────
// Systemweiter OAuth-Flow (Plattform-Admin, Policy im Controller);
// eigene Verbindung + Schreib-Scopes, getrennt vom Dokumenteingang.
Route::middleware(['web', 'auth'])->group(function (): void {
    Route::post('admin/backup-targets/google/oauth/start', [\App\Plugins\GoogleDrive\Http\Controllers\GoogleDriveBackupTargetController::class, 'startOAuth'])->name('admin.backup-targets.google.oauth.start');
    Route::get('admin/backup-targets/google/oauth/callback', [\App\Plugins\GoogleDrive\Http\Controllers\GoogleDriveBackupTargetController::class, 'oauthCallback'])->name('admin.backup-targets.google.oauth.callback');
});
