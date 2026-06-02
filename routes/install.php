<?php
/*
 * Created on   : Mon Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : install.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Http\Controllers\Install\InstallController;
use App\Http\Middleware\RedirectIfInstalled;
use Illuminate\Support\Facades\Route;

/*
 * Routen des Web-Installers. Laufen im web-Stack (Session/CSRF), werden aber
 * durch RedirectIfInstalled gesperrt, sobald die Lock-Datei storage/installed
 * existiert. PrepareInstaller (web-prepend) stellt vorab APP_KEY sowie
 * datei-basierte Session/Cache sicher.
 */

Route::middleware(RedirectIfInstalled::class)
    ->prefix('install')
    ->name('install.')
    ->group(function (): void {
        Route::get('/', [InstallController::class, 'index'])->name('index');

        Route::get('/application', [InstallController::class, 'application'])->name('application');
        Route::post('/application', [InstallController::class, 'storeApplication'])->name('application.store');

        Route::get('/database', [InstallController::class, 'database'])->name('database');
        Route::post('/database', [InstallController::class, 'storeDatabase'])->name('database.store');

        Route::get('/admin', [InstallController::class, 'admin'])->name('admin');
        Route::post('/admin', [InstallController::class, 'storeAdmin'])->name('admin.store');

        Route::get('/mail', [InstallController::class, 'mail'])->name('mail');
        Route::post('/mail', [InstallController::class, 'storeMail'])->name('mail.store');

        Route::get('/integrations', [InstallController::class, 'integrations'])->name('integrations');
        Route::post('/integrations', [InstallController::class, 'storeIntegrations'])->name('integrations.store');
        Route::post('/integrations/vapid-keys', [InstallController::class, 'generateVapidKeys'])->name('integrations.vapid');

        Route::get('/finish', [InstallController::class, 'finish'])->name('finish');
        Route::post('/finish', [InstallController::class, 'complete'])->name('complete');
    });
