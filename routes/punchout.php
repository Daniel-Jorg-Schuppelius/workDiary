<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : punchout.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Http\Controllers\B2bCatalog\OciCartController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Shop-Rücksprünge des Einkaufs (MVP-096 OCI, MVP-1071 IDS-Connect)
|--------------------------------------------------------------------------
| Der Lieferanten-Shop POSTet den Warenkorb cross-site; das Sitzungscookie
| (SameSite=Lax) fehlt. Mit Sitzung setzte die Antwort ein neues Cookie und
| meldete den Einkäufer ab — daher ohne Sitzung, Cookies und CSRF.
| Autorisiert wird über die signierte HOOK_URL (OCI) bzw. das Einmal-Token
| (IDS); die Meldungen zeigt danach `oci-carts.result` in der Sitzung an.
*/

Route::post('oci-carts/return', [OciCartController::class, 'hookReturn'])
    ->middleware(['signed', 'throttle:12,1'])
    ->name('oci-carts.return');

Route::post('oci-carts/ids/{token}', [OciCartController::class, 'idsReturn'])
    ->where('token', '[A-Za-z0-9]{40}')
    ->middleware('throttle:12,1')
    ->name('oci-carts.ids-return');
