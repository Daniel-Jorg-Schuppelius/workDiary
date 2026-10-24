<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : routes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Plugins\Taiga\Http\Controllers\TaigaAdminController;
use Illuminate\Support\Facades\Route;

// Admin-Recht prüft der Controller; Adresse und Zugang stehen in den Plugin-Einstellungen.
Route::middleware(['web', 'auth', \App\Http\Middleware\EnforcePlanModules::class])
    ->prefix('admin/taiga')
    ->name('admin.taiga.')
    ->group(static function (): void {
        Route::get('/', [TaigaAdminController::class, 'index'])->name('index');
        Route::post('verify', [TaigaAdminController::class, 'verify'])->name('verify');
        Route::post('disconnect', [TaigaAdminController::class, 'disconnect'])->name('disconnect');
        Route::post('sync', [TaigaAdminController::class, 'syncNow'])->name('sync');

        Route::get('links/create', [TaigaAdminController::class, 'createLink'])->name('links.create');
        Route::post('links', [TaigaAdminController::class, 'storeLink'])->name('links.store');
        Route::get('links/{link}/preview', [TaigaAdminController::class, 'preview'])->name('links.preview');
        Route::post('links/{link}/confirm', [TaigaAdminController::class, 'confirm'])->name('links.confirm');
        Route::post('links/{link}/attach', [TaigaAdminController::class, 'attach'])->name('links.attach');
        Route::post('links/{link}/pause', [TaigaAdminController::class, 'pause'])->name('links.pause');
        Route::post('links/{link}/resume', [TaigaAdminController::class, 'resume'])->name('links.resume');
        Route::delete('links/{link}', [TaigaAdminController::class, 'destroyLink'])->name('links.destroy');
    });
