<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : routes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Plugins\RemoteSupport\Http\Controllers\{RemoteSupportAssetController, RemoteSupportPendingController};
use Illuminate\Support\Facades\Route;

/**
 * Plugin-eigene Routen (Geräte-IDs + manueller Import). Geladen vom
 * {@see \App\Plugins\RemoteSupport\RemoteSupportServiceProvider}. Berechtigung
 * wird im Controller per Gate('update', $asset) geprüft.
 */
Route::middleware(['web', 'auth'])->group(function (): void {
    Route::post('assets/{asset}/remote-support/id', [RemoteSupportAssetController::class, 'saveId'])
        ->name('assets.remote-support.id');
    Route::delete('assets/{asset}/remote-support/{provider}', [RemoteSupportAssetController::class, 'forgetId'])
        ->name('assets.remote-support.forget');
    Route::post('assets/{asset}/remote-support/sync', [RemoteSupportAssetController::class, 'sync'])
        ->name('assets.remote-support.sync');

    // Inbox: Verbindungen ohne zugeordnetes Gerät
    Route::get('admin/remote-support/pending', [RemoteSupportPendingController::class, 'index'])
        ->name('admin.remote-support.pending.index');
    Route::post('admin/remote-support/pending/assign-existing', [RemoteSupportPendingController::class, 'assignExisting'])
        ->name('admin.remote-support.pending.assign-existing');
    Route::post('admin/remote-support/pending/assign-new', [RemoteSupportPendingController::class, 'assignNew'])
        ->name('admin.remote-support.pending.assign-new');
    Route::post('admin/remote-support/pending/dismiss', [RemoteSupportPendingController::class, 'dismiss'])
        ->name('admin.remote-support.pending.dismiss');
});
