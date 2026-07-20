<?php
/*
 * Created on   : Sun Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : careers.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use App\Http\Controllers\Careers\{PublicApplicationController, PublicCareerController};
use App\Http\Middleware\Careers\ResolveCareerPortal;
use App\Http\Middleware\EnforcePlanModules;
use Illuminate\Support\Facades\Route;

/**
 * Öffentlicher Karrierebereich (Feature 068, MVP-437). Läuft im schlanken
 * `careers`-Middleware-Stack (kein Auth/Org-Context/Locale/2FA) — siehe
 * bootstrap/app.php. Die Organisation wird ausschließlich über den
 * mandantengebundenen `{org}`-Slug aufgelöst; `EnforcePlanModules` prüft
 * zusätzlich `module.applications` (careers.* in config/plans.php).
 */
Route::prefix('karriere/{org}')
    ->name('careers.')
    ->middleware([ResolveCareerPortal::class, EnforcePlanModules::class])
    ->group(function (): void {
        Route::get('/', [PublicCareerController::class, 'index'])
            ->middleware('throttle:careers-view')->name('index');

        Route::get('stellen/{posting}', [PublicCareerController::class, 'show'])
            ->middleware('throttle:careers-view')->name('show');

        Route::get('stellen/{posting}/embed', [PublicCareerController::class, 'embed'])
            ->middleware('throttle:careers-view')->name('embed');

        Route::post('stellen/{posting}/bewerben', [PublicApplicationController::class, 'store'])
            ->middleware('throttle:careers-submit')->name('apply');

        Route::get('stellen/{posting}/danke', [PublicApplicationController::class, 'confirmation'])
            ->middleware('throttle:careers-view')->name('confirmation');
    });
