<?php
/*
 * Created on   : Tue Sep 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : routes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Plugins\S3\Http\Controllers\S3BackupTargetController;
use Illuminate\Support\Facades\Route;

// S3-kompatibles Backupziel (Feature 123, MVP-726); nur Plattform-Admin —
// die Policy prüft das, die Route trägt nur den Anmeldezwang.
Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('admin/backup-targets/s3/connect', [S3BackupTargetController::class, 'connectForm'])
        ->name('admin.backup-targets.s3.connect-form');
    Route::post('admin/backup-targets/s3/connect', [S3BackupTargetController::class, 'connect'])
        ->name('admin.backup-targets.s3.connect');
});
