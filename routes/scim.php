<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : scim.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Http\Controllers\Scim\{ScimConfigController, ScimGroupController, ScimUserController};
use Illuminate\Support\Facades\Route;

/**
 * SCIM 2.0 (Feature 057, MVP-121). Prefix `scim/v2`, Auth + Org-Bindung +
 * Enterprise-Gating über {@see \App\Http\Middleware\AuthenticateScim} (in
 * bootstrap/app.php gebunden). Sessionlos, ohne CSRF — reine Bearer-Token-API.
 */
Route::get('ServiceProviderConfig', [ScimConfigController::class, 'serviceProviderConfig'])->name('scim.config');
Route::get('ResourceTypes', [ScimConfigController::class, 'resourceTypes'])->name('scim.resourcetypes');

Route::get('Users', [ScimUserController::class, 'index'])->name('scim.users.index');
Route::post('Users', [ScimUserController::class, 'store'])->name('scim.users.store');
Route::get('Users/{id}', [ScimUserController::class, 'show'])->name('scim.users.show');
Route::put('Users/{id}', [ScimUserController::class, 'replace'])->name('scim.users.replace');
Route::patch('Users/{id}', [ScimUserController::class, 'patch'])->name('scim.users.patch');
Route::delete('Users/{id}', [ScimUserController::class, 'destroy'])->name('scim.users.destroy');

Route::get('Groups', [ScimGroupController::class, 'index'])->name('scim.groups.index');
Route::post('Groups', [ScimGroupController::class, 'store'])->name('scim.groups.store');
Route::get('Groups/{id}', [ScimGroupController::class, 'show'])->name('scim.groups.show');
Route::put('Groups/{id}', [ScimGroupController::class, 'replace'])->name('scim.groups.replace');
Route::patch('Groups/{id}', [ScimGroupController::class, 'patch'])->name('scim.groups.patch');
Route::delete('Groups/{id}', [ScimGroupController::class, 'destroy'])->name('scim.groups.destroy');

// Sammeloperationen (RFC 7644 §3.7). Klein gehalten; ServiceProviderConfig meldet die Limits.
Route::post('Bulk', [ScimUserController::class, 'bulk'])->name('scim.bulk');
