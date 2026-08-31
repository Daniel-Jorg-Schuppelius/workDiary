<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CtiWebhookController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{CtiConnection, Organization};
use App\Services\Cti\{CtiCallService, CtiNormalizerResolver};
use Illuminate\Http\{JsonResponse, Request};
use OpenApi\Attributes as OA;

/**
 * Eingehender CTI-Webhook (Feature 056, MVP-118). Sessionlos und ohne CSRF;
 * autorisiert über einen Token im Pfad (nur SHA-256-Hash gespeichert). Der
 * providerspezifische Normalizer übersetzt das Payload; abgeschlossene Anrufe
 * bekannter Kunden werden als Kommunikationseintrag protokolliert (nur
 * Metadaten). Zwischenzustände werden ignoriert.
 */
class CtiWebhookController extends Controller {
    #[OA\Post(
        path: '/cti/webhook/{token}',
        summary: 'CTI-Webhook (Anruf-Ereignis)',
        description: 'Auth über Gerätetoken im Pfad; nur Metadaten, nie Gesprächsinhalte.',
        tags: ['Ingest'],
        parameters: [new OA\Parameter(name: 'token', in: 'path', required: true, description: 'Webhook-Token der CTI-Verbindung', schema: new OA\Schema(type: 'string'))],
        requestBody: new OA\RequestBody(required: false, description: 'Providerspezifisches Anruf-Ereignis (z. B. sipgate)', content: new OA\JsonContent(type: 'object')),
        responses: [
            new OA\Response(response: 200, description: 'OK (status: recorded/ignored)'),
            new OA\Response(response: 404, description: 'Unbekannter oder inaktiver Token'),
            new OA\Response(response: 503, description: 'Wartungsmodus (Retry-After)'),
        ],
    )]
    public function __invoke(Request $request, string $token, CtiNormalizerResolver $resolver, CtiCallService $service): JsonResponse {
        $connection = CtiConnection::query()->withoutGlobalScopes()
            ->where('webhook_token_hash', CtiConnection::hashToken($token))
            ->where('active', true)
            ->first();
        if (! $connection instanceof CtiConnection) {
            return response()->json(['status' => 'ignored'], 404);
        }

        // Org-Kontext für nachgelagerte (scoped) Operationen binden.
        $organization = Organization::query()->whereKey($connection->organization_id)->first();
        if ($organization instanceof Organization) {
            app()->instance('currentOrganization', $organization);

            // Wartungsmodus (Rang 65): nur bei explizitem block_ingest pausieren.
            if ($organization->maintenanceBlocksIngest()) {
                return response()->json(['status' => 'maintenance'], 503, ['Retry-After' => '3600']);
            }
            // Mandantensperre (Sicherheitsscan 2026-08-23, S-42): greift sonst
            // nur bei angemeldeten Zugriffen — dieser Weg hat keine Sitzung.
            if (! $organization->publicSurfacesAvailable()) {
                return response()->json(['status' => 'tenant_blocked'], 423, ['Retry-After' => '3600']);
            }
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->all();
        $call = $resolver->for($connection->provider)->normalize($payload);
        if ($call === null) {
            return response()->json(['status' => 'ignored']); // kein terminales Ereignis
        }

        $result = $service->record($connection, $call);

        return response()->json(['status' => $result]);
    }
}
