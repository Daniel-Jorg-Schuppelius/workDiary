<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : routes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use App\Plugins\Redmine\Http\Controllers\RedmineAdminController;
use Illuminate\Support\Facades\Route;

// Admin-Recht prüft der Controller; Basis-URL und API-Schlüssel stehen in den Plugin-Einstellungen.
Route::middleware(['web', 'auth', \App\Http\Middleware\EnforcePlanModules::class])
    ->prefix('admin/redmine')
    ->name('admin.redmine.')
    ->group(static function (): void {
        Route::get('/', [RedmineAdminController::class, 'index'])->name('index');
        Route::post('verify', [RedmineAdminController::class, 'verify'])->name('verify');
        Route::post('disconnect', [RedmineAdminController::class, 'disconnect'])->name('disconnect');
        Route::post('sync', [RedmineAdminController::class, 'syncNow'])->name('sync');

        Route::get('links/create', [RedmineAdminController::class, 'createLink'])->name('links.create');
        Route::post('links', [RedmineAdminController::class, 'storeLink'])->name('links.store');
        Route::get('links/{link}/preview', [RedmineAdminController::class, 'preview'])->name('links.preview');
        Route::post('links/{link}/confirm', [RedmineAdminController::class, 'confirm'])->name('links.confirm');
        Route::post('links/{link}/attach', [RedmineAdminController::class, 'attach'])->name('links.attach');
        Route::post('links/{link}/pause', [RedmineAdminController::class, 'pause'])->name('links.pause');
        Route::post('links/{link}/resume', [RedmineAdminController::class, 'resume'])->name('links.resume');
        Route::post('links/{link}/times', [RedmineAdminController::class, 'toggleTimes'])->name('links.times');
        Route::delete('links/{link}', [RedmineAdminController::class, 'destroyLink'])->name('links.destroy');

        Route::post('users', [RedmineAdminController::class, 'mapUser'])->name('users.map');
        Route::post('users/unmap', [RedmineAdminController::class, 'unmapUser'])->name('users.unmap');
    });
