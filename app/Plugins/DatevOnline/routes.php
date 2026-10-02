<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : routes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Plugins\DatevOnline\Http\Controllers\DatevOnlineAdminController;
use Illuminate\Support\Facades\Route;

/** Plugin-Routen DATEV-Online (MVP-122), geladen vom {@see \App\Plugins\DatevOnline\DatevOnlineServiceProvider}. */
Route::middleware(['web', 'auth'])->prefix('admin/datev-online')->name('admin.datev-online.')->group(function (): void {
    Route::get('/', [DatevOnlineAdminController::class, 'index'])->name('index');
    Route::post('oauth/start', [DatevOnlineAdminController::class, 'startOAuth'])->name('oauth.start');
    Route::get('oauth/callback', [DatevOnlineAdminController::class, 'oauthCallback'])->name('oauth.callback');
    Route::post('disconnect', [DatevOnlineAdminController::class, 'disconnect'])->name('disconnect');
    Route::post('client', [DatevOnlineAdminController::class, 'selectClient'])->name('client');
    Route::post('documents', [DatevOnlineAdminController::class, 'updateDocuments'])->name('documents');
    Route::post('documents/upload', [DatevOnlineAdminController::class, 'uploadNow'])->middleware('throttle:6,1')->name('documents.upload');
    Route::post('batches/{batch}/transfer', [DatevOnlineAdminController::class, 'transferBatch'])->middleware('throttle:12,1')->name('batches.transfer');
    Route::post('jobs/refresh', [DatevOnlineAdminController::class, 'refreshJobs'])->middleware('throttle:12,1')->name('jobs.refresh');
});
