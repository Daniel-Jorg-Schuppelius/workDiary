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

use App\Plugins\Jira\Http\Controllers\JiraAdminController;
use Illuminate\Support\Facades\Route;

// Admin-Recht prüft der Controller; der OAuth-Callback läuft in der Sitzung (state org- und nutzergebunden).
Route::middleware(['web', 'auth', \App\Http\Middleware\EnforcePlanModules::class])
    ->prefix('admin/jira')
    ->name('admin.jira.')
    ->group(static function (): void {
        Route::get('/', [JiraAdminController::class, 'index'])->name('index');
        Route::post('oauth/start', [JiraAdminController::class, 'startOAuth'])->name('oauth.start');
        Route::get('oauth/callback', [JiraAdminController::class, 'oauthCallback'])->name('oauth.callback');
        Route::post('disconnect', [JiraAdminController::class, 'disconnect'])->name('disconnect');
        Route::post('source', [JiraAdminController::class, 'chooseSource'])->name('source');
        Route::post('sync', [JiraAdminController::class, 'syncNow'])->name('sync');

        Route::get('links/create', [JiraAdminController::class, 'createLink'])->name('links.create');
        Route::post('links', [JiraAdminController::class, 'storeLink'])->name('links.store');
        Route::get('links/{link}/preview', [JiraAdminController::class, 'preview'])->name('links.preview');
        Route::post('links/{link}/confirm', [JiraAdminController::class, 'confirm'])->name('links.confirm');
        Route::post('links/{link}/attach', [JiraAdminController::class, 'attach'])->name('links.attach');
        Route::post('links/{link}/pause', [JiraAdminController::class, 'pause'])->name('links.pause');
        Route::post('links/{link}/resume', [JiraAdminController::class, 'resume'])->name('links.resume');
        Route::delete('links/{link}', [JiraAdminController::class, 'destroyLink'])->name('links.destroy');
    });
