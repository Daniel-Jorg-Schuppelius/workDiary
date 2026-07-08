<?php
/*
 * Created on   : Sat Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ZammadWebhookController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Zammad\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\{Organization, ZammadConnection};
use App\Plugins\Zammad\Contracts\ZammadGatewayFactory;
use App\Plugins\Zammad\Services\ZammadTicketImporter;
use Illuminate\Http\{JsonResponse, Request};
use Throwable;

/**
 * Zammad-Webhook (Feature 060, MVP-129): stößt einen idempotenten Ticket-Import
 * an. Sessionlos und ohne CSRF — Autorisierung ausschließlich über die
 * HMAC-Signatur (`X-Hub-Signature: sha1=…`) des Raw-Bodys gegen das je Anbindung
 * hinterlegte, verschlüsselte Shared-Secret. Der Webhook ist nur ein Anstoß:
 * fällt er aus, holt das Polling ({@see \App\Plugins\Zammad\Console\ZammadSyncCommand})
 * lückenlos nach (DoD 060). Es werden nie Aufgaben gelöscht.
 */
class ZammadWebhookController extends Controller {
    public function __invoke(Request $request, int $connection, ZammadGatewayFactory $factory, ZammadTicketImporter $importer): JsonResponse {
        $conn = ZammadConnection::query()->withoutGlobalScopes()->whereKey($connection)->first();
        if (! $conn instanceof ZammadConnection || ! $conn->isActive() || $conn->webhook_secret === null) {
            // 404 statt 401: keine Auskunft über Existenz/Zustand der Anbindung.
            return response()->json(['status' => 'ignored'], 404);
        }

        if (! $this->signatureValid($request, (string) $conn->webhook_secret)) {
            return response()->json(['status' => 'invalid_signature'], 403);
        }

        // Org-Kontext für nachgelagerte (scoped) Operationen binden.
        $org = Organization::query()->whereKey($conn->organization_id)->first();
        if ($org instanceof Organization) {
            app()->instance('currentOrganization', $org);
        }

        try {
            $result = $importer->import($conn, $factory->for($conn));
        } catch (Throwable) {
            // Kein Datenverlust: Polling holt nach. 202 = angenommen, aber nicht abgeschlossen.
            return response()->json(['status' => 'deferred'], 202);
        }

        return response()->json(['status' => 'ok'] + $result);
    }

    /** Konstantzeit-Vergleich der Zammad-Signatur (sha1=HMAC-SHA1(body, secret)). */
    private function signatureValid(Request $request, string $secret): bool {
        $header = (string) $request->header('X-Hub-Signature', '');
        if (! str_starts_with($header, 'sha1=')) {
            return false;
        }
        $expected = 'sha1=' . hash_hmac('sha1', $request->getContent(), $secret);

        return hash_equals($expected, $header);
    }
}
