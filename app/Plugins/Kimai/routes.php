<?php
/*
 * Created on   : Mon Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : routes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Plugins\Kimai\Http\Controllers\KimaiController;
use Illuminate\Support\Facades\Route;

/**
 * Plugin-eigene Routen (Kimai-Import/-Export). Geladen vom
 * {@see \App\Plugins\Kimai\KimaiServiceProvider}. Admin-Berechtigung wird im
 * Controller geprüft. Unzugeordnetes wird in der universellen Zuordnungs-Inbox
 * (admin.integration.inbox) aufgelöst.
 */
Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('admin/kimai', [KimaiController::class, 'index'])->name('admin.kimai.index');
    Route::post('admin/kimai/import-csv', [KimaiController::class, 'uploadCsv'])->name('admin.kimai.import-csv');
    Route::post('admin/kimai/import-api', [KimaiController::class, 'importApi'])->name('admin.kimai.import-api');
    Route::post('admin/kimai/export-api', [KimaiController::class, 'exportApi'])->name('admin.kimai.export-api');
});
