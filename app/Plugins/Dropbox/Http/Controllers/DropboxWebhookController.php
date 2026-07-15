<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DropboxWebhookController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Dropbox\Http\Controllers;

use App\Enums\CloudIntake\CloudIntakeProvider;
use App\Http\Controllers\Controller;
use App\Models\CloudIntake\CloudDocumentConnection;
use App\Plugins\Dropbox\DropboxConfig;
use App\Services\CloudIntake\IntakeWakeSignal;
use Illuminate\Http\{Request, Response};

/**
 * Dropbox-Webhook (Feature 080, MVP-353) — NUR Aufwecksignal, nie Wahrheit:
 * der Cursor-Lauf des Runners bleibt maßgeblich (adr-webhook-security.md).
 *
 * - GET: Verifikations-Challenge wird als text/plain zurückgegeben.
 * - POST: `X-Dropbox-Signature` = HMAC-SHA256 des Roh-Bodys mit dem
 *   installationsweiten App-Secret (Konstantzeit-Vergleich). Der Payload
 *   nennt nur Account-IDs — der Mandant wird ausschließlich serverseitig
 *   über `external_account_id` gespeicherter Verbindungen aufgelöst
 *   (Confused-Deputy-Muster), nie aus dem Payload übernommen.
 */
class DropboxWebhookController extends Controller {
    public function verify(Request $request): Response {
        return response((string) $request->query('challenge', ''), 200, [
            'Content-Type' => 'text/plain',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function __invoke(Request $request, IntakeWakeSignal $wake): Response {
        $secret = DropboxConfig::resolve()['client_secret'];
        $signature = (string) $request->header('X-Dropbox-Signature', '');
        $expected = hash_hmac('sha256', (string) $request->getContent(), $secret);

        if ($secret === '' || $signature === '' || ! hash_equals($expected, $signature)) {
            abort(403);
        }

        /** @var list<string> $accounts */
        $accounts = (array) $request->json('list_folder.accounts', []);

        // Aufweck-Flag je betroffener Verbindung — org-agnostisch, weil das
        // Flag nur einen ohnehin org-gebundenen Cursor-Lauf vorzieht.
        CloudDocumentConnection::query()
            ->withoutGlobalScopes()
            ->where('provider', CloudIntakeProvider::Dropbox->value)
            ->whereIn('external_account_id', array_filter(array_map('strval', $accounts)))
            ->pluck('id')
            ->each(fn ($id) => $wake->signal((int) $id));

        return response('', 200);
    }
}
