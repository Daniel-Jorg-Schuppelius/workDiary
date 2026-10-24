<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : routes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Plugins\FreeScout\Http\Controllers\FreeScoutAdminController;
use Illuminate\Support\Facades\Route;

// Admin-Recht prüft der Controller; Adresse und Zugang stehen in den Plugin-Einstellungen.
Route::middleware(['web', 'auth', \App\Http\Middleware\EnforcePlanModules::class])
    ->prefix('admin/freescout')
    ->name('admin.freescout.')
    ->group(static function (): void {
        Route::get('/', [FreeScoutAdminController::class, 'index'])->name('index');
        Route::post('verify', [FreeScoutAdminController::class, 'verify'])->name('verify');
        Route::post('disconnect', [FreeScoutAdminController::class, 'disconnect'])->name('disconnect');
        Route::post('sync', [FreeScoutAdminController::class, 'syncNow'])->name('sync');

        Route::get('links/create', [FreeScoutAdminController::class, 'createLink'])->name('links.create');
        Route::post('links', [FreeScoutAdminController::class, 'storeLink'])->name('links.store');
        Route::get('links/{link}/preview', [FreeScoutAdminController::class, 'preview'])->name('links.preview');
        Route::post('links/{link}/confirm', [FreeScoutAdminController::class, 'confirm'])->name('links.confirm');
        Route::post('links/{link}/attach', [FreeScoutAdminController::class, 'attach'])->name('links.attach');
        Route::post('links/{link}/pause', [FreeScoutAdminController::class, 'pause'])->name('links.pause');
        Route::post('links/{link}/resume', [FreeScoutAdminController::class, 'resume'])->name('links.resume');
        Route::delete('links/{link}', [FreeScoutAdminController::class, 'destroyLink'])->name('links.destroy');
    });
