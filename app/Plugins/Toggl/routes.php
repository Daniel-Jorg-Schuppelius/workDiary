<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : routes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Plugins\Toggl\Http\Controllers\TogglController;
use Illuminate\Support\Facades\Route;

/**
 * Plugin-eigene Routen (Toggl-Import + Inbox). Geladen vom
 * {@see \App\Plugins\Toggl\TogglServiceProvider}. Admin-Berechtigung wird im
 * Controller geprüft.
 */
Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('admin/toggl', [TogglController::class, 'index'])->name('admin.toggl.index');
    Route::post('admin/toggl/sync', [TogglController::class, 'sync'])->name('admin.toggl.sync');
    Route::post('admin/toggl/import-csv', [TogglController::class, 'uploadCsv'])->name('admin.toggl.import-csv');
    Route::post('admin/toggl/pending/assign', [TogglController::class, 'assign'])->name('admin.toggl.pending.assign');
    Route::post('admin/toggl/pending/dismiss', [TogglController::class, 'dismiss'])->name('admin.toggl.pending.dismiss');

    Route::get('admin/toggl/mappings', [TogglController::class, 'mappings'])->name('admin.toggl.mappings.index');
    Route::post('admin/toggl/mappings/{reference}', [TogglController::class, 'updateMapping'])->name('admin.toggl.mappings.update');
    Route::post('admin/toggl/mappings/{reference}/delete', [TogglController::class, 'deleteMapping'])->name('admin.toggl.mappings.delete');

    Route::get('admin/toggl/import-export', [TogglController::class, 'importExport'])->name('admin.toggl.import-export');
    Route::post('admin/toggl/import-export', [TogglController::class, 'runImportExport'])->name('admin.toggl.import-export.run');
});
