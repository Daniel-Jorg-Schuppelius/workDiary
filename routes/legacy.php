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

use App\Legacy\Http\Controllers\{LegacyAccountController, LegacyArchiveController, LegacyCallcenterController, LegacyDiaryController, LegacyMigrationController, LegacyNotdienstController, LegacyOnCallController, LegacySupportArchiveController, LegacySupportCustomerController, LegacySupportMailSettingController, LegacySupportOrderController, LegacySupportPortalAuthController, LegacySupportPortalOrderController, LegacyUserAdminController};
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
    // Reines IP-Limit (Sicherheitsaudit 2026-09-13): Der Controller drosselt
    // bereits je Benutzername und antwortet dabei mit einer verstaendlichen
    // Meldung. Was fehlte, war die Schranke gegen Passwort-Spraying — viele
    // verschiedene Namen von derselben Adresse, gegen die Klartext-Passwoerter
    // der Legacy-Datenbank. Die Schwelle liegt bewusst ueber der des
    // Controllers, damit der normale Fehlversuch weiter freundlich antwortet.
    Route::post('login', [LegacyCallcenterController::class, 'login'])
        ->middleware('throttle:legacy-login')
        ->name('login.submit');
    Route::post('logout', [LegacyCallcenterController::class, 'logout'])->name('logout');
    Route::get('notdienst', [LegacyCallcenterController::class, 'notdienstPlan'])
        ->middleware('legacy.callcenter.auth')
        ->name('notdienst');
});

// Support-Portal, Kundenseite: eigene Anmeldung gegen kp_user, ohne Konto im neuen System.
Route::prefix('legacy/support-portal')->name('legacy.portal.')->group(function (): void {
    Route::get('/', fn() => redirect()->route('legacy.portal.orders.index'))->name('home');
    Route::get('login', [LegacySupportPortalAuthController::class, 'showLoginForm'])->name('login');
    // IP-Limit wie beim Callcenter gegen Passwort-Spraying; der Controller drosselt je Name.
    Route::post('login', [LegacySupportPortalAuthController::class, 'login'])
        ->middleware('throttle:legacy-login')
        ->name('login.submit');
    Route::post('logout', [LegacySupportPortalAuthController::class, 'logout'])->name('logout');

    Route::middleware('legacy.portal.auth')->group(function (): void {
        Route::get('orders', [LegacySupportPortalOrderController::class, 'index'])->name('orders.index');
        Route::get('orders/create', [LegacySupportPortalOrderController::class, 'create'])->name('orders.create');
        Route::get('orders/{order}', [LegacySupportPortalOrderController::class, 'show'])
            ->whereNumber('order')
            ->name('orders.show');
        Route::get('password', [LegacySupportPortalAuthController::class, 'editPassword'])->name('password.edit');

        Route::middleware('legacy.write')->group(function (): void {
            Route::post('orders', [LegacySupportPortalOrderController::class, 'store'])->name('orders.store');
            Route::put('password', [LegacySupportPortalAuthController::class, 'updatePassword'])->name('password.update');
        });
    });
});

// Authentifizierte Legacy-Routen
Route::middleware('auth')->group(function (): void {
    // Admin-Migration-Dashboard (admin-only via Gate in Controller).
    // Bewusst OHNE access.legacy, damit Admins ohne Legacy-Account es nutzen können.
    Route::get('admin/legacy-migration', [LegacyMigrationController::class, 'index'])->name('admin.legacy-migration.index');
    Route::post('admin/legacy-migration', [LegacyMigrationController::class, 'run'])->name('admin.legacy-migration.run');

    // Legacy-Bereich: nur für User mit legacy_user_id (oder Admins).
    Route::middleware('access.legacy')->group(function (): void {
        // Read-only-Routen (immer erlaubt)
        Route::get('legacy/diary/week', [LegacyDiaryController::class, 'week'])->name('legacy.diary.week');
        Route::get('legacy/overview', fn() => redirect()->route('legacy.callcenter.notdienst'))->name('legacy.overview.index');
        Route::get('legacy/archive', [LegacyArchiveController::class, 'index'])->name('legacy.archive.index');
        Route::get('legacy/archive/week', [LegacyArchiveController::class, 'week'])->name('legacy.archive.week');
        Route::get('legacy/archive/{entry}', [LegacyArchiveController::class, 'show'])
            ->whereNumber('entry')
            ->name('legacy.archive.show');

        // Support-Portal, Bürosicht (Legacy-Admins und Plattformbetrieb, Prüfung im Controller).
        Route::get('legacy/support/orders', [LegacySupportOrderController::class, 'index'])->name('legacy.support.orders.index');
        Route::get('legacy/support/orders/{order}', [LegacySupportOrderController::class, 'show'])
            ->whereNumber('order')
            ->name('legacy.support.orders.show');
        Route::get('legacy/support/customers', [LegacySupportCustomerController::class, 'index'])->name('legacy.support.customers.index');
        Route::get('legacy/support/archive', [LegacySupportArchiveController::class, 'index'])->name('legacy.support.archive.index');
        Route::get('legacy/support/archive/{order}', [LegacySupportArchiveController::class, 'show'])
            ->whereNumber('order')
            ->name('legacy.support.archive.show');

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

            Route::resource('legacy/support/orders', LegacySupportOrderController::class)
                ->except(['index', 'show'])
                ->names('legacy.support.orders')
                ->parameters(['orders' => 'order']);

            Route::resource('legacy/support/customers', LegacySupportCustomerController::class)
                ->except(['index', 'show'])
                ->names('legacy.support.customers')
                ->parameters(['customers' => 'customer']);

            Route::post('legacy/support/archive/run', [LegacySupportArchiveController::class, 'run'])->name('legacy.support.archive.run');
            Route::get('legacy/support/mail', [LegacySupportMailSettingController::class, 'edit'])->name('legacy.support.mail.edit');
            Route::put('legacy/support/mail', [LegacySupportMailSettingController::class, 'update'])->name('legacy.support.mail.update');
        });
    });
});
