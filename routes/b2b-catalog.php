<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : b2b-catalog.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Http\Controllers\B2bCatalog\B2bPunchoutController;
use App\Http\Middleware\B2bCatalog\ResolveB2bCatalogOrganization;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Öffentlicher OCI-Punchout-Katalog (Feature 099, MVP-457)
|--------------------------------------------------------------------------
| Sessionlos, mandantengebunden über den {org}-Slug (Muster Karriereportal).
| Inaktives Modul oder unbekannter Mandant → 404 (ResolveB2bCatalogOrganization).
| Der Einstieg ist ein Cross-Site-POST des Einkaufssystems (kein CSRF im
| Stack); Browse/Warenkorb schützt das verschlüsselte, zeitbegrenzte Token.
*/

Route::prefix('b2b-katalog/{org}')
    ->name('b2b-punchout.')
    ->middleware([ResolveB2bCatalogOrganization::class])
    ->group(function (): void {
        Route::post('punchout', [B2bPunchoutController::class, 'punchout'])
            ->middleware('throttle:b2b-login')
            ->name('entry');
        Route::get('katalog', [B2bPunchoutController::class, 'browse'])
            ->middleware('throttle:b2b-view')
            ->name('browse');
        Route::post('warenkorb', [B2bPunchoutController::class, 'transfer'])
            ->middleware('throttle:b2b-view')
            ->name('transfer');
    });
