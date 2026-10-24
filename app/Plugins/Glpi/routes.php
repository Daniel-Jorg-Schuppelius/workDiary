<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : routes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Plugins\Glpi\Http\Controllers\GlpiAdminController;
use Illuminate\Support\Facades\Route;

// Admin-Recht prüft der Controller; Adresse und Zugang stehen in den Plugin-Einstellungen.
Route::middleware(['web', 'auth', \App\Http\Middleware\EnforcePlanModules::class])
    ->prefix('admin/glpi')
    ->name('admin.glpi.')
    ->group(static function (): void {
        Route::get('/', [GlpiAdminController::class, 'index'])->name('index');
        Route::post('verify', [GlpiAdminController::class, 'verify'])->name('verify');
        Route::post('disconnect', [GlpiAdminController::class, 'disconnect'])->name('disconnect');
        Route::post('sync', [GlpiAdminController::class, 'syncNow'])->name('sync');

        Route::get('links/create', [GlpiAdminController::class, 'createLink'])->name('links.create');
        Route::post('links', [GlpiAdminController::class, 'storeLink'])->name('links.store');
        Route::get('links/{link}/preview', [GlpiAdminController::class, 'preview'])->name('links.preview');
        Route::post('links/{link}/confirm', [GlpiAdminController::class, 'confirm'])->name('links.confirm');
        Route::post('links/{link}/attach', [GlpiAdminController::class, 'attach'])->name('links.attach');
        Route::post('links/{link}/pause', [GlpiAdminController::class, 'pause'])->name('links.pause');
        Route::post('links/{link}/resume', [GlpiAdminController::class, 'resume'])->name('links.resume');
        Route::delete('links/{link}', [GlpiAdminController::class, 'destroyLink'])->name('links.destroy');
    });
