<?php
/*
 * Created on   : Mon Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : routes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Plugins\OpenProject\Http\Controllers\OpenProjectController;
use Illuminate\Support\Facades\Route;

/**
 * Plugin-eigene Routen (OpenProject-Sync, Import-Inbox, Rückbuchung, Mappings).
 * Geladen vom {@see \App\Plugins\OpenProject\OpenProjectServiceProvider}.
 * Admin-Berechtigung wird im Controller geprüft.
 */
Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('admin/openproject', [OpenProjectController::class, 'index'])->name('admin.openproject.index');

    // Struktur-Sync (Projekte + Work Packages) und Zeit-Import.
    Route::post('admin/openproject/sync-structure', [OpenProjectController::class, 'syncStructure'])->name('admin.openproject.sync-structure');
    Route::post('admin/openproject/sync', [OpenProjectController::class, 'sync'])->name('admin.openproject.sync');

    // Rückbuchung erfasster Zeiten nach OpenProject.
    Route::get('admin/openproject/push', [OpenProjectController::class, 'push'])->name('admin.openproject.push');
    Route::post('admin/openproject/push', [OpenProjectController::class, 'runPush'])->name('admin.openproject.push.run');

    // Mapping-Verwaltung (Projekt-/Work-Package-Zuordnungen).
    Route::get('admin/openproject/mappings', [OpenProjectController::class, 'mappings'])->name('admin.openproject.mappings.index');
    Route::post('admin/openproject/mappings/{reference}', [OpenProjectController::class, 'updateMapping'])->name('admin.openproject.mappings.update');
    Route::post('admin/openproject/mappings/{reference}/delete', [OpenProjectController::class, 'deleteMapping'])->name('admin.openproject.mappings.delete');
});
