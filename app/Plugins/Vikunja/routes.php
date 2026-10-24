<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : routes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Plugins\Vikunja\Http\Controllers\VikunjaAdminController;
use Illuminate\Support\Facades\Route;

// Admin-Recht prüft der Controller; Adresse und Zugang stehen in den Plugin-Einstellungen.
Route::middleware(['web', 'auth', \App\Http\Middleware\EnforcePlanModules::class])
    ->prefix('admin/vikunja')
    ->name('admin.vikunja.')
    ->group(static function (): void {
        Route::get('/', [VikunjaAdminController::class, 'index'])->name('index');
        Route::post('verify', [VikunjaAdminController::class, 'verify'])->name('verify');
        Route::post('disconnect', [VikunjaAdminController::class, 'disconnect'])->name('disconnect');
        Route::post('sync', [VikunjaAdminController::class, 'syncNow'])->name('sync');

        Route::get('links/create', [VikunjaAdminController::class, 'createLink'])->name('links.create');
        Route::post('links', [VikunjaAdminController::class, 'storeLink'])->name('links.store');
        Route::get('links/{link}/preview', [VikunjaAdminController::class, 'preview'])->name('links.preview');
        Route::post('links/{link}/confirm', [VikunjaAdminController::class, 'confirm'])->name('links.confirm');
        Route::post('links/{link}/attach', [VikunjaAdminController::class, 'attach'])->name('links.attach');
        Route::post('links/{link}/pause', [VikunjaAdminController::class, 'pause'])->name('links.pause');
        Route::post('links/{link}/resume', [VikunjaAdminController::class, 'resume'])->name('links.resume');
        Route::delete('links/{link}', [VikunjaAdminController::class, 'destroyLink'])->name('links.destroy');
    });
