<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : routes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Plugins\Webdav\Http\Controllers\{WebdavAdminController, WebdavBackupTargetController, WebdavConflictController};
use Illuminate\Support\Facades\Route;

/**
 * Plugin-eigene Routen (Feature 058, MVP-127): Admin-Panel (Ablage-Anbindung +
 * manueller Voll-Spiegellauf). Admin-Autorisierung wird im Controller geprüft.
 * Rein ausgehend — kein öffentlicher/unauthentifizierter Endpunkt.
 */
Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('admin/webdav', [WebdavAdminController::class, 'index'])->name('admin.webdav.index');

    Route::post('admin/webdav/connection', [WebdavAdminController::class, 'store'])->name('admin.webdav.connection.store');
    Route::post('admin/webdav/disconnect', [WebdavAdminController::class, 'disconnect'])->name('admin.webdav.disconnect');

    // Manueller Voll-Spiegellauf über alle freigegebenen Dokumente (auditiert).
    Route::post('admin/webdav/mirror', [WebdavAdminController::class, 'mirror'])->name('admin.webdav.mirror');

    // Generisches WebDAV-Backupziel (Feature 123, MVP-612); Plattform-Admin.
    Route::get('admin/backup-targets/webdav/connect', [WebdavBackupTargetController::class, 'connectForm'])
        ->name('admin.backup-targets.webdav.connect-form');
    Route::post('admin/backup-targets/webdav/connect', [WebdavBackupTargetController::class, 'connect'])
        ->name('admin.backup-targets.webdav.connect');

    // Konfliktauflösung aus der Zuordnungs-Inbox (Rang 18), je Einzel-Item.
    Route::post('admin/webdav/conflict/{item}/overwrite', [WebdavConflictController::class, 'overwrite'])->name('admin.webdav.conflict.overwrite');
    Route::post('admin/webdav/conflict/{item}/import', [WebdavConflictController::class, 'import'])->name('admin.webdav.conflict.import');
    Route::post('admin/webdav/conflict/{item}/detach', [WebdavConflictController::class, 'detach'])->name('admin.webdav.conflict.detach');
});
