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

use App\Plugins\Asana\Http\Controllers\AsanaAdminController;
use Illuminate\Support\Facades\Route;

// Admin-Recht prüft der Controller; der OAuth-Callback läuft in der Sitzung (state org- und nutzergebunden).
Route::middleware(['web', 'auth', \App\Http\Middleware\EnforcePlanModules::class])
    ->prefix('admin/asana')
    ->name('admin.asana.')
    ->group(static function (): void {
        Route::get('/', [AsanaAdminController::class, 'index'])->name('index');
        Route::post('oauth/start', [AsanaAdminController::class, 'startOAuth'])->name('oauth.start');
        Route::get('oauth/callback', [AsanaAdminController::class, 'oauthCallback'])->name('oauth.callback');
        Route::post('disconnect', [AsanaAdminController::class, 'disconnect'])->name('disconnect');
        Route::post('source', [AsanaAdminController::class, 'chooseSource'])->name('source');
        Route::post('sync', [AsanaAdminController::class, 'syncNow'])->name('sync');

        Route::get('links/create', [AsanaAdminController::class, 'createLink'])->name('links.create');
        Route::post('links', [AsanaAdminController::class, 'storeLink'])->name('links.store');
        Route::get('links/{link}/preview', [AsanaAdminController::class, 'preview'])->name('links.preview');
        Route::post('links/{link}/confirm', [AsanaAdminController::class, 'confirm'])->name('links.confirm');
        Route::post('links/{link}/attach', [AsanaAdminController::class, 'attach'])->name('links.attach');
        Route::post('links/{link}/pause', [AsanaAdminController::class, 'pause'])->name('links.pause');
        Route::post('links/{link}/resume', [AsanaAdminController::class, 'resume'])->name('links.resume');
        Route::delete('links/{link}', [AsanaAdminController::class, 'destroyLink'])->name('links.destroy');
    });
