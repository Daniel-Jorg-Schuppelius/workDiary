<?php

/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : legacy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use App\Legacy\Http\Controllers\LegacyAccountController;
use App\Legacy\Http\Controllers\LegacyArchiveController;
use App\Legacy\Http\Controllers\LegacyCallcenterController;
use App\Legacy\Http\Controllers\LegacyDiaryController;
use App\Legacy\Http\Controllers\LegacyMigrationController;
use App\Legacy\Http\Controllers\LegacyNotdienstController;
use App\Legacy\Http\Controllers\LegacyOnCallController;
use App\Legacy\Http\Controllers\LegacyUserAdminController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Legacy Module Routes
|--------------------------------------------------------------------------
|
| Routen für das isolierte Legacy-Modul (App\Legacy\*). Wird über
| bootstrap/app.php zusätzlich zum Web-Stack geladen, damit alle Web-
| Middleware (SecurityHeaders, SetLocale, SetOrganizationContext,
| ForcePasswordChange) gilt.
|
*/

// Öffentliche Callcenter-Login-Routen
Route::prefix('legacy/callcenter')->name('legacy.callcenter.')->group(function (): void {
    Route::get('login', [LegacyCallcenterController::class, 'showLoginForm'])->name('login');
    Route::post('login', [LegacyCallcenterController::class, 'login'])->name('login.submit');
    Route::post('logout', [LegacyCallcenterController::class, 'logout'])->name('logout');
    Route::get('notdienst', [LegacyCallcenterController::class, 'notdienstPlan'])
        ->middleware('legacy.callcenter.auth')
        ->name('notdienst');
});

// Authentifizierte Legacy-Routen
Route::middleware('auth')->group(function (): void {
    // Admin-Migration-Dashboard (admin-only via Gate in Controller)
    Route::get('admin/legacy-migration', [LegacyMigrationController::class, 'index'])->name('admin.legacy-migration.index');
    Route::post('admin/legacy-migration', [LegacyMigrationController::class, 'run'])->name('admin.legacy-migration.run');

    // Read-only-Routen (immer erlaubt)
    Route::get('legacy/diary/week', [LegacyDiaryController::class, 'week'])->name('legacy.diary.week');
    Route::get('legacy/overview', fn () => redirect()->route('legacy.callcenter.notdienst'))->name('legacy.overview.index');
    Route::get('legacy/archive', [LegacyArchiveController::class, 'index'])->name('legacy.archive.index');
    Route::get('legacy/archive/week', [LegacyArchiveController::class, 'week'])->name('legacy.archive.week');
    Route::get('legacy/archive/{entry}', [LegacyArchiveController::class, 'show'])
        ->whereNumber('entry')
        ->name('legacy.archive.show');

    // Schreib-Routen (nur wenn LEGACY_WRITE_ENABLED=true)
    Route::middleware('legacy.write')->group(function (): void {
        Route::get('legacy/account/password', [LegacyAccountController::class, 'editPassword'])->name('legacy.account.password.edit');
        Route::post('legacy/account/password', [LegacyAccountController::class, 'updatePassword'])->name('legacy.account.password.update');

        Route::resource('legacy/users', LegacyUserAdminController::class)
            ->except(['show'])
            ->names('legacy.users')
            ->parameters(['users' => 'user']);

        Route::resource('legacy/diary', LegacyDiaryController::class)
            ->names('legacy.diary')
            ->parameters(['diary' => 'entry']);

        Route::post('legacy/diary-bulk', [LegacyDiaryController::class, 'bulk'])->name('legacy.diary.bulk');

        Route::resource('legacy/on-call', LegacyOnCallController::class)
            ->except(['show'])
            ->names('legacy.oncall')
            ->parameters(['on-call' => 'oncall']);

        Route::resource('legacy/notdienst', LegacyNotdienstController::class)
            ->except(['show'])
            ->names('legacy.notdienst')
            ->parameters(['notdienst' => 'notdienst']);

        Route::post('legacy/archive/run', [LegacyArchiveController::class, 'run'])->name('legacy.archive.run');
    });
});
