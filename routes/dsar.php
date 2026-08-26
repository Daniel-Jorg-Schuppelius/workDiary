<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : dsar.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use App\Http\Controllers\Privacy\PublicDsarController;
use App\Http\Middleware\Privacy\ResolveDsarPortal;
use Illuminate\Support\Facades\Route;

/**
 * Oeffentliches Betroffenen-Selbstmeldeportal (Feature 043, MVP-728, G11).
 * Laeuft im schlanken `dsar`-Middleware-Stack (kein Auth/Org-Context/Locale/
 * 2FA) — siehe bootstrap/app.php. Die Organisation kommt ausschliesslich aus
 * dem Portal-Slug; {@see ResolveDsarPortal} setzt Default-Deny (404 bei
 * unbekanntem, deaktiviertem oder unlizenziertem Portal) durch.
 */
// Neutrale Landingpage ohne Slug — erklaert das Verfahren generisch und gibt
// bewusst weder Organisation noch Portal-Existenz preis.
Route::get('datenschutz/anfrage', [PublicDsarController::class, 'landing'])
    ->middleware('throttle:dsar-view')
    ->name('dsar.landing');

// Adressbestaetigung aus der Eingangsbestaetigung (signierte, befristete URL).
// MUSS vor den {portal}-Routen stehen, damit „bestaetigung" nicht als Slug gilt.
Route::get('datenschutz/anfrage/bestaetigung/{dsr}', [PublicDsarController::class, 'confirm'])
    ->middleware(['signed', 'throttle:dsar-view'])
    ->name('dsar.confirm');

Route::prefix('datenschutz/anfrage')
    ->name('dsar.')
    ->middleware(ResolveDsarPortal::class)
    ->group(function (): void {
        Route::get('{portal}', [PublicDsarController::class, 'show'])
            ->middleware('throttle:dsar-view')
            ->name('portal');

        Route::post('{portal}', [PublicDsarController::class, 'store'])
            ->middleware('throttle:dsar-submit')
            ->name('store');

        Route::get('{portal}/erfolg', [PublicDsarController::class, 'receipt'])
            ->middleware('throttle:dsar-view')
            ->name('receipt');
    });
