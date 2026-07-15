<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GoogleDriveWebhookController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\GoogleDrive\Http\Controllers;

use App\Enums\CloudIntake\CloudIntakeProvider;
use App\Http\Controllers\Controller;
use App\Models\CloudIntake\CloudDocumentConnection;
use App\Services\CloudIntake\IntakeWakeSignal;
use Illuminate\Http\{Request, Response};

/**
 * Google-Drive-Watch-Channel (Feature 080, MVP-355) — NUR Aufwecksignal:
 * Zuordnung über `X-Goog-Channel-ID` (= subscription_id der Verbindung) +
 * `X-Goog-Channel-Token` (Konstantzeit-Vergleich gegen das gespeicherte
 * Secret). Payload-Inhalte werden nicht ausgewertet; maßgeblich bleibt der
 * changes-Lauf über den persistierten pageToken.
 */
class GoogleDriveWebhookController extends Controller {
    public function __invoke(Request $request, IntakeWakeSignal $wake): Response {
        $channelId = (string) $request->header('X-Goog-Channel-ID', '');
        $channelToken = (string) $request->header('X-Goog-Channel-Token', '');

        if ($channelId === '' || $channelToken === '') {
            abort(403);
        }

        $connection = CloudDocumentConnection::query()
            ->withoutGlobalScopes()
            ->where('provider', CloudIntakeProvider::Google->value)
            ->where('subscription_id', $channelId)
            ->first();

        $secret = (string) ($connection->webhook_secret ?? '');
        if ($connection === null || $secret === '' || ! hash_equals($secret, $channelToken)) {
            abort(403);
        }

        $wake->signal((int) $connection->id);

        return response('', 200);
    }
}
