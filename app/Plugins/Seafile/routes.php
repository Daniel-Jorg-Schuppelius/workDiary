<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : routes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Plugins\Seafile\Http\Controllers\{SeafileBackupTargetController, SeafileIntakeController};
use Illuminate\Support\Facades\Route;

// Rechte prüft der Controller: Dokumenteingang je Organisation, Backupziel nur Plattform-Admin.
Route::middleware(['web', 'auth'])->group(static function (): void {
    Route::get('admin/cloud-intake/seafile/connect', [SeafileIntakeController::class, 'connectForm'])
        ->name('admin.cloud-intake.seafile.connect-form');
    Route::post('admin/cloud-intake/seafile/connect', [SeafileIntakeController::class, 'connect'])
        ->name('admin.cloud-intake.seafile.connect');

    Route::get('admin/backup-targets/seafile/connect', [SeafileBackupTargetController::class, 'connectForm'])
        ->name('admin.backup-targets.seafile.connect-form');
    Route::post('admin/backup-targets/seafile/connect', [SeafileBackupTargetController::class, 'connect'])
        ->name('admin.backup-targets.seafile.connect');
});
