<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillbeeApiClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Billbee\Api;

use App\Plugins\Billbee\BillbeePlugin;
use App\Plugins\Support\{PluginApiClient, PluginHttpFactory};
use Illuminate\Http\Client\Response;

/**
 * Typisierte Oberfläche der Billbee-REST-API (MVP-433/434; OpenAPI-Fixture
 * tests/Fixtures/Plugins/Billbee/openapi.json). HTTP über
 * {@see PluginApiClient} (php-api-toolkit: Retry/Backoff inkl. Retry-After —
 * Billbee drosselt hart auf 2 req/s, daher festes Request-Intervall).
 *
 * Auth: Header `X-Billbee-Api-Key` + HTTP Basic (Billbee-Nutzer +
 * API-Passwort). Antworten kommen PascalCase-umschlagen:
 * `{Data: …, Paging: {Page, TotalPages, …}, ErrorMessage}`.
 */
class BillbeeApiClient {
    // Gemeinsame guard()-Fehlerbehandlung (Vollaudit 2026-07, N33).
    use \App\Plugins\Support\GuardsPluginApiResponses;

    private ?PluginApiClient $api = null;

    public function __construct(
        private readonly PluginHttpFactory $http,
        private readonly string $apiKey,
        private readonly string $username,
        private readonly string $apiPassword,
        private readonly string $baseUrl,
        private readonly float $requestInterval,
    ) {}

    /**
     * GET /api/v1/orders — Aufholpunkt über modifiedAtMin (ISO-8601).
     *
     * @return array{data: array<int, mixed>, page: int, total_pages: int}
     */
    public function orders(?string $modifiedAtMin, int $page, int $pageSize): array {
        $query = array_filter([
            'modifiedAtMin' => $modifiedAtMin,
            'page' => $page,
            'pageSize' => $pageSize,
        ], static fn($value): bool => $value !== null);

        $body = $this->guard($this->authed('get', '/api/v1/orders', ['query' => $query]), '/api/v1/orders');

        return $this->paged($body);
    }

    /**
     * GET /api/v1/products — SKU-/Produktliste für das Artikel-Mapping.
     *
     * @return array{data: array<int, mixed>, page: int, total_pages: int}
     */
    public function products(int $page, int $pageSize): array {
        $body = $this->guard(
            $this->authed('get', '/api/v1/products', ['query' => ['page' => $page, 'pageSize' => $pageSize]]),
            '/api/v1/products',
        );

        return $this->paged($body);
    }

    /**
     * POST /api/v1/products/updatestock — setzt den ABSOLUTEN Zielbestand
     * (NewQuantity) je SKU; Wiederholungen sind dadurch von Natur aus
     * idempotent (kein Marker-Scan nötig — Unterschied zu JTL).
     *
     * @return array<string, mixed>
     */
    public function updateStock(string $sku, float $newQuantity, ?string $reason = null): array {
        return (array) $this->guard(
            $this->authed('post', '/api/v1/products/updatestock', ['json' => array_filter([
                'Sku' => $sku,
                'NewQuantity' => $newQuantity,
                'Reason' => $reason,
            ], static fn($value): bool => $value !== null)]),
            '/api/v1/products/updatestock',
        );
    }

    // ── Intern ──────────────────────────────────────────────────────────

    /**
     * Billbee-Umschlag normalisieren: {Data, Paging{Page, TotalPages}}.
     *
     * @param array<mixed> $body
     * @return array{data: array<int, mixed>, page: int, total_pages: int}
     */
    private function paged(array $body): array {
        $data = $body['Data'] ?? [];
        $paging = (array) ($body['Paging'] ?? []);

        return [
            'data' => is_array($data) && array_is_list($data) ? $data : [],
            'page' => (int) ($paging['Page'] ?? 1),
            'total_pages' => (int) ($paging['TotalPages'] ?? 1),
        ];
    }

    /** @param array<string, mixed> $options */
    private function authed(string $method, string $path, array $options = []): Response {
        $options['headers'] = array_merge(
            (array) ($options['headers'] ?? []),
            [
                'X-Billbee-Api-Key' => $this->apiKey,
                'Authorization' => 'Basic ' . base64_encode($this->username . ':' . $this->apiPassword),
            ],
        );

        return $this->api()->requestResponse($method, $this->baseUrl . $path, $options);
    }

    /** Ein Exemplar je Client, damit das 0,5-s-Intervall zwischen Requests wirkt. */
    private function api(): PluginApiClient {
        if ($this->api === null) {
            $this->api = $this->http->client(BillbeePlugin::ID, $this->baseUrl, $this->requestInterval);
        }

        return $this->api;
    }

    /** @return class-string<\App\Plugins\Support\PluginApiException> */
    protected function apiExceptionClass(): string {
        return BillbeeApiException::class;
    }

    protected function apiLabel(): string {
        return 'Billbee';
    }
}
