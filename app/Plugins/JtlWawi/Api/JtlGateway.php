<?php
/*
 * Created on   : Sat Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JtlGateway.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\JtlWawi\Api;

use App\Models\JtlConnection;
use App\Plugins\JtlWawi\JtlWawiPlugin;
use App\Plugins\Support\{PluginApiClient, PluginHttpFactory};
use Carbon\CarbonInterface;
use Illuminate\Http\Client\Response;

/**
 * Typisierte Oberfläche der JTL-Wawi-API v2 (Feature 078, MVP-317) für beide
 * Betriebsarten. Vertragsgrundlage: veröffentlichte OpenAPI 2.0/2.1
 * (Abweichungsregister in Feature 078).
 *
 * - OnPremise: `Authorization: Wawi <key>` + Pflicht-Header `api-version`;
 *   Registrierungs-Endpunkte laufen unauthentifiziert mit `x-challengecode`.
 * - Cloud: `Authorization: Bearer <JWT>` + `X-Tenant-ID`; der Token kommt
 *   vom {@see JtlCloudTokenService}.
 *
 * Nicht-2xx-Antworten werfen {@see JtlApiException} (Status + JTL-errorCode,
 * nie Payload/Secrets in der Message). Transportfehler propagieren als
 * Guzzle-Exceptions — Retry/Backoff inkl. 429/Retry-After übernimmt der
 * {@see PluginApiClient} (php-api-toolkit).
 */
class JtlGateway {
    private PluginApiClient $client;

    /** Geprüfte Basis-URL; Aufrufe nutzen absolute URLs, damit der
     * OnPremise-Basispfad `/api/eazybusiness` nie durch Guzzles
     * RFC-3986-Auflösung absoluter Pfade verloren geht. */
    private string $baseUrl;

    public function __construct(
        private readonly JtlConnection $connection,
        PluginHttpFactory $http,
        private readonly JtlCloudTokenService $tokens,
    ) {
        $this->baseUrl = JtlUrlGuard::baseUrlFor($connection);
        $this->client = $http->client(JtlWawiPlugin::ID, $this->baseUrl);
    }

    /** @return array{version?: string, timestamp?: string, tenant?: string, type?: string} */
    public function info(): array {
        return $this->decode($this->get('/v2/info'));
    }

    /**
     * App-Registrierung anstoßen (OnPremise; unauthentifiziert, nur
     * Challenge-Code). Antwort: registrationRequestId + Status-Enum.
     *
     * @param  array<string, mixed>  $payload
     * @return array{appId?: string, registrationRequestId?: string, status?: int}
     */
    public function registerApp(array $payload, string $challengeCode): array {
        $response = $this->client->postJson($this->baseUrl . '/v2/authentication', $payload, [
            'headers' => $this->registrationHeaders($challengeCode),
        ]);

        return $this->decode($this->guard($response, 'POST /v2/authentication'));
    }

    /**
     * Registrierungsstatus abfragen; bei Freigabe enthält die Antwort den
     * einmalig ausgegebenen API-Key (`token.apiKey`) + `grantedScopes`.
     *
     * @return array{requestStatusInfo?: array{status?: int}, token?: array{apiKey?: string}, grantedScopes?: array<int, string>}
     */
    public function fetchRegistration(string $registrationId, string $challengeCode): array {
        $response = $this->client->getResponse($this->baseUrl . '/v2/authentication/' . rawurlencode($registrationId), [], [
            'headers' => $this->registrationHeaders($challengeCode),
        ]);

        return $this->decode($this->guard($response, 'GET /v2/authentication/{id}'));
    }

    /** @return array<string, mixed> Paginierten Envelope (items/totalPages/...) */
    public function warehouses(int $page = 1, int $pageSize = 100): array {
        return $this->decode($this->get('/v2/warehouses', [
            'pageNumber' => $page,
            'pageSize' => $pageSize,
        ]));
    }

    /**
     * Bestände je Artikel/Lager (paginierter Envelope).
     *
     * @return array<string, mixed>
     */
    public function stocks(?string $itemId = null, ?string $warehouseId = null, int $page = 1, int $pageSize = 100): array {
        return $this->decode($this->get('/v2/stocks', array_filter([
            'itemId' => $itemId,
            'warehouseId' => $warehouseId,
            'pageNumber' => $page,
            'pageSize' => $pageSize,
        ], static fn ($value) => $value !== null)));
    }

    /**
     * Bestandsänderungs-Journal seit `startDate` (paginierter Envelope) —
     * Polling-Instrument und Reconciliation-Quelle.
     *
     * @return array<string, mixed>
     */
    public function stockChanges(CarbonInterface $startDate, ?string $itemId = null, int $page = 1, int $pageSize = 100): array {
        return $this->decode($this->get('/v2/stocks/changes', array_filter([
            'itemId' => $itemId,
            'startDate' => $startDate->toIso8601String(),
            'pageNumber' => $page,
            'pageSize' => $pageSize,
        ], static fn ($value) => $value !== null)));
    }

    /**
     * Bestandsbuchung als Mengen-Delta (`POST /v2/stocks`). Die API kennt
     * keinen Idempotenz-Key — der Aufrufer MUSS vorher über
     * {@see stockChanges()} nach seinem Quellmarker suchen.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function postStockAdjustment(array $payload): array {
        $response = $this->client->postJson($this->baseUrl . '/v2/stocks', $payload, [
            'headers' => $this->authHeaders(),
        ]);

        return $this->decode($this->guard($response, 'POST /v2/stocks'));
    }

    /**
     * Artikel-Liste (v2.1-Pre-Release; in v2.0 nicht vorhanden → 404/405
     * wirft {@see JtlApiException} mit `isMissingEndpoint()`).
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function items(array $query = [], int $page = 1, int $pageSize = 100): array {
        return $this->decode($this->get('/v2/items', array_merge($query, [
            'pageNumber' => $page,
            'pageSize' => $pageSize,
        ])));
    }

    /**
     * Ausgangsrechnungen (`GET /v2/salesinvoices`, paginierter Envelope).
     *
     * Die Faktura-Domäne ist in der v2.0-OpenAPI **kommandolastig**
     * (`salesinvoice`-Kommandos, Feature 078 „Weitere Ressourcen") — ob eine
     * Instanz die Liste anbietet, hängt an ihrem API-Stand. Fehlt der
     * Endpunkt, antwortet JTL mit 404/405 und {@see JtlApiException::isMissingEndpoint()}
     * ist true; der Aufrufer bricht dann sichtbar ab, statt eine andere
     * Ressource als Rechnung auszugeben. Zeitfilter heißt bei JTL
     * `createdSince` (die Aufträge kennen kein `changedSince`).
     *
     * @return array<string, mixed>
     */
    public function salesInvoices(?CarbonInterface $createdSince = null, int $page = 1, int $pageSize = 100): array {
        return $this->decode($this->get('/v2/salesinvoices', array_filter([
            'createdSince' => $createdSince?->toIso8601String(),
            'pageNumber' => $page,
            'pageSize' => $pageSize,
        ], static fn ($value) => $value !== null)));
    }

    /** @return array<string, mixed> Einzelner Artikel (v2.0: einziger Artikel-Leseweg). */
    public function item(string $itemId): array {
        return $this->decode($this->get('/v2/items/' . rawurlencode($itemId)));
    }

    /**
     * GET mit Auth-Headern beider Betriebsarten.
     *
     * @param  array<string, mixed>  $query
     */
    private function get(string $path, array $query = []): Response {
        $response = $this->client->getResponse($this->baseUrl . $path, $query, [
            'headers' => $this->authHeaders(),
        ]);

        return $this->guard($response, 'GET ' . $path);
    }

    /**
     * Header für reguläre (authentifizierte) Aufrufe.
     *
     * @return array<string, string>
     */
    private function authHeaders(): array {
        $headers = $this->commonHeaders();

        if ($this->connection->isOnPremise()) {
            $headers['Authorization'] = 'Wawi ' . (string) $this->connection->api_key;
        } else {
            $headers['Authorization'] = 'Bearer ' . $this->tokens->ensureToken($this->connection);
        }

        return $headers;
    }

    /**
     * Header für die unauthentifizierte App-Registrierung (OnPremise).
     *
     * @return array<string, string>
     */
    private function registrationHeaders(string $challengeCode): array {
        return array_merge($this->commonHeaders(), ['x-challengecode' => $challengeCode]);
    }

    /** @return array<string, string> */
    private function commonHeaders(): array {
        $headers = [
            'x-appid' => (string) config('plugins.' . JtlWawiPlugin::ID . '.app_id'),
            'x-appversion' => (string) config('app.version', '1.0.0'),
        ];

        if ($this->connection->isOnPremise()) {
            // Pflicht-Header der OnPremise-Instanz; Cloud steuert die Version
            // über den /v2-Pfad.
            $headers['api-version'] = $this->connection->api_version;
        }

        if (trim((string) $this->connection->tenant_id) !== '') {
            $headers['x-tenant-id'] = (string) $this->connection->tenant_id;
        }

        if (trim((string) $this->connection->company_id) !== '') {
            $headers['x-companyid'] = (string) $this->connection->company_id;
        }

        return $headers;
    }

    /** Wirft {@see JtlApiException} bei Nicht-2xx — Message ohne Payload/Secrets. */
    private function guard(Response $response, string $endpoint): Response {
        if ($response->successful()) {
            return $response;
        }

        $errorCode = null;
        $body = $response->json();
        if (is_array($body) && isset($body['errorCode']) && is_string($body['errorCode'])) {
            $errorCode = $body['errorCode'];
        }

        throw new JtlApiException(
            sprintf('JTL-Wawi-API: %s antwortete mit HTTP %d%s.', $endpoint, $response->status(), $errorCode !== null ? " ({$errorCode})" : ''),
            $response->status(),
            $errorCode,
        );
    }

    /** @return array<string, mixed> */
    private function decode(Response $response): array {
        $json = $response->json();

        return is_array($json) ? $json : [];
    }
}
