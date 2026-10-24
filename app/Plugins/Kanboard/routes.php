<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : routes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Plugins\Kanboard\Http\Controllers\KanboardAdminController;
use Illuminate\Support\Facades\Route;

// Admin-Recht prüft der Controller; Adresse und Zugang stehen in den Plugin-Einstellungen.
Route::middleware(['web', 'auth', \App\Http\Middleware\EnforcePlanModules::class])
    ->prefix('admin/kanboard')
    ->name('admin.kanboard.')
    ->group(static function (): void {
        Route::get('/', [KanboardAdminController::class, 'index'])->name('index');
        Route::post('verify', [KanboardAdminController::class, 'verify'])->name('verify');
        Route::post('disconnect', [KanboardAdminController::class, 'disconnect'])->name('disconnect');
        Route::post('sync', [KanboardAdminController::class, 'syncNow'])->name('sync');

        Route::get('links/create', [KanboardAdminController::class, 'createLink'])->name('links.create');
        Route::post('links', [KanboardAdminController::class, 'storeLink'])->name('links.store');
        Route::get('links/{link}/preview', [KanboardAdminController::class, 'preview'])->name('links.preview');
        Route::post('links/{link}/confirm', [KanboardAdminController::class, 'confirm'])->name('links.confirm');
        Route::post('links/{link}/attach', [KanboardAdminController::class, 'attach'])->name('links.attach');
        Route::post('links/{link}/pause', [KanboardAdminController::class, 'pause'])->name('links.pause');
        Route::post('links/{link}/resume', [KanboardAdminController::class, 'resume'])->name('links.resume');
        Route::delete('links/{link}', [KanboardAdminController::class, 'destroyLink'])->name('links.destroy');
    });
