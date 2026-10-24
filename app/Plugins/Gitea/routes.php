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

use App\Plugins\Gitea\Http\Controllers\GiteaAdminController;
use Illuminate\Support\Facades\Route;

// Admin-Recht prüft der Controller; Basis-URL und Token stehen in den Plugin-Einstellungen.
Route::middleware(['web', 'auth', \App\Http\Middleware\EnforcePlanModules::class])
    ->prefix('admin/gitea')
    ->name('admin.gitea.')
    ->group(static function (): void {
        Route::get('/', [GiteaAdminController::class, 'index'])->name('index');
        Route::post('verify', [GiteaAdminController::class, 'verify'])->name('verify');
        Route::post('disconnect', [GiteaAdminController::class, 'disconnect'])->name('disconnect');
        Route::post('sync', [GiteaAdminController::class, 'syncNow'])->name('sync');

        Route::get('links/create', [GiteaAdminController::class, 'createLink'])->name('links.create');
        Route::post('links', [GiteaAdminController::class, 'storeLink'])->name('links.store');
        Route::get('links/{link}/preview', [GiteaAdminController::class, 'preview'])->name('links.preview');
        Route::post('links/{link}/confirm', [GiteaAdminController::class, 'confirm'])->name('links.confirm');
        Route::post('links/{link}/attach', [GiteaAdminController::class, 'attach'])->name('links.attach');
        Route::post('links/{link}/pause', [GiteaAdminController::class, 'pause'])->name('links.pause');
        Route::post('links/{link}/resume', [GiteaAdminController::class, 'resume'])->name('links.resume');
        Route::delete('links/{link}', [GiteaAdminController::class, 'destroyLink'])->name('links.destroy');
    });
