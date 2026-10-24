<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : routes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Plugins\Plane\Http\Controllers\PlaneAdminController;
use Illuminate\Support\Facades\Route;

// Admin-Recht prüft der Controller; Adresse und Zugang stehen in den Plugin-Einstellungen.
Route::middleware(['web', 'auth', \App\Http\Middleware\EnforcePlanModules::class])
    ->prefix('admin/plane')
    ->name('admin.plane.')
    ->group(static function (): void {
        Route::get('/', [PlaneAdminController::class, 'index'])->name('index');
        Route::post('verify', [PlaneAdminController::class, 'verify'])->name('verify');
        Route::post('disconnect', [PlaneAdminController::class, 'disconnect'])->name('disconnect');
        Route::post('sync', [PlaneAdminController::class, 'syncNow'])->name('sync');

        Route::get('links/create', [PlaneAdminController::class, 'createLink'])->name('links.create');
        Route::post('links', [PlaneAdminController::class, 'storeLink'])->name('links.store');
        Route::get('links/{link}/preview', [PlaneAdminController::class, 'preview'])->name('links.preview');
        Route::post('links/{link}/confirm', [PlaneAdminController::class, 'confirm'])->name('links.confirm');
        Route::post('links/{link}/attach', [PlaneAdminController::class, 'attach'])->name('links.attach');
        Route::post('links/{link}/pause', [PlaneAdminController::class, 'pause'])->name('links.pause');
        Route::post('links/{link}/resume', [PlaneAdminController::class, 'resume'])->name('links.resume');
        Route::delete('links/{link}', [PlaneAdminController::class, 'destroyLink'])->name('links.destroy');
    });
