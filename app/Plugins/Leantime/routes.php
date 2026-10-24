<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : routes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Plugins\Leantime\Http\Controllers\LeantimeAdminController;
use Illuminate\Support\Facades\Route;

// Admin-Recht prüft der Controller; Adresse und Zugang stehen in den Plugin-Einstellungen.
Route::middleware(['web', 'auth', \App\Http\Middleware\EnforcePlanModules::class])
    ->prefix('admin/leantime')
    ->name('admin.leantime.')
    ->group(static function (): void {
        Route::get('/', [LeantimeAdminController::class, 'index'])->name('index');
        Route::post('verify', [LeantimeAdminController::class, 'verify'])->name('verify');
        Route::post('disconnect', [LeantimeAdminController::class, 'disconnect'])->name('disconnect');
        Route::post('sync', [LeantimeAdminController::class, 'syncNow'])->name('sync');

        Route::get('links/create', [LeantimeAdminController::class, 'createLink'])->name('links.create');
        Route::post('links', [LeantimeAdminController::class, 'storeLink'])->name('links.store');
        Route::get('links/{link}/preview', [LeantimeAdminController::class, 'preview'])->name('links.preview');
        Route::post('links/{link}/confirm', [LeantimeAdminController::class, 'confirm'])->name('links.confirm');
        Route::post('links/{link}/attach', [LeantimeAdminController::class, 'attach'])->name('links.attach');
        Route::post('links/{link}/pause', [LeantimeAdminController::class, 'pause'])->name('links.pause');
        Route::post('links/{link}/resume', [LeantimeAdminController::class, 'resume'])->name('links.resume');
        Route::delete('links/{link}', [LeantimeAdminController::class, 'destroyLink'])->name('links.destroy');
    });
