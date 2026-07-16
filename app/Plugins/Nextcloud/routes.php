<?php
/*
 * Created on   : Wed Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : routes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Plugins\Nextcloud\Http\Controllers\{NextcloudBackupTargetController, NextcloudIntakeController};
use Illuminate\Support\Facades\Route;

/**
 * Plugin-Routen (Feature 080 MVP-382 / Feature 017 MVP-383). Nextcloud wird —
 * anders als die OAuth-Provider — per Zugangsdaten (Server-URL, Nutzer,
 * App-Passwort) angebunden; kein Redirect-Flow, kein öffentlicher Webhook.
 * Autorisierung wird im jeweiligen Controller geprüft (Org- bzw. Plattform-Admin).
 */
Route::middleware(['web', 'auth'])->group(function (): void {
    // Dokumenteingang (Organisation).
    Route::get('admin/cloud-intake/nextcloud/connect', [NextcloudIntakeController::class, 'connectForm'])
        ->name('admin.cloud-intake.nextcloud.connect-form');
    Route::post('admin/cloud-intake/nextcloud/connect', [NextcloudIntakeController::class, 'connect'])
        ->name('admin.cloud-intake.nextcloud.connect');

    // Backupziel (systemweit, Plattform-Admin).
    Route::get('admin/backup-targets/nextcloud/connect', [NextcloudBackupTargetController::class, 'connectForm'])
        ->name('admin.backup-targets.nextcloud.connect-form');
    Route::post('admin/backup-targets/nextcloud/connect', [NextcloudBackupTargetController::class, 'connect'])
        ->name('admin.backup-targets.nextcloud.connect');
});
