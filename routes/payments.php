<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : payments.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Http\Controllers\Invoicing\OnlinePaymentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Online-Zahlung von Rechnungen (MVP-1067)
|--------------------------------------------------------------------------
| Ohne Anmeldung, Sitzung und CSRF: Den Zahlungslink öffnet der Kunde aus PDF,
| Mail oder Portal, den Webhook ruft der Anbieter. Autorisiert wird über das
| Token des Links; der Webhook stößt nur die Nachfrage beim Anbieter an.
*/

Route::get('zahlen/{token}', [OnlinePaymentController::class, 'show'])
    ->where('token', '[A-Za-z0-9]{40}')
    ->middleware('throttle:30,1')
    ->name('payments.show');

Route::get('zahlen/{token}/fertig', [OnlinePaymentController::class, 'done'])
    ->where('token', '[A-Za-z0-9]{40}')
    ->middleware('throttle:30,1')
    ->name('payments.done');

Route::post('webhooks/online-payment/{provider}', [OnlinePaymentController::class, 'webhook'])
    ->where('provider', '[a-z0-9_-]{2,40}')
    ->middleware('throttle:120,1')
    ->name('payments.webhook');
