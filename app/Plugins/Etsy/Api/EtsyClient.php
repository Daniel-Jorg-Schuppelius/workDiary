<?php
/*
 * Created on   : Tue Aug 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EtsyClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Etsy\Api;

use APIToolkit\API\Authentication\OAuth2\OAuth2BearerAuthentication;
use App\Models\EtsyConnection;
use App\Plugins\Etsy\EtsyPlugin;
use App\Plugins\Support\{ConnectionTokenStore, GuardsPluginApiResponses, PluginApiClient, PluginHttpFactory};

/**
 * Typisierte Oberfläche der Etsy Open API v3 (Feature 101; W0-Preflight
 * 2026-08-04 gegen Spec + Doku). HTTP über {@see PluginApiClient}
 * (php-api-toolkit: Retry/Backoff inkl. Retry-After — Etsy drosselt auf
 * 10 req/s + 10.000 req/Tag mit 429).
 *
 * Auth: JEDER Request trägt `x-api-key: <keystring>:<shared_secret>`
 * (Spec securitySchemes.api_key); OAuth-Endpunkte zusätzlich
 * `Authorization: Bearer {user_id}.{token}` über den org-gebundenen
 * {@see ConnectionTokenStore} inkl. transparentem Refresh (Etsys
 * Refresh-Token ROTIERT — der Store speichert den neuen mit).
 * Listen kommen als `{count, results}`-Umschlag.
 */
class EtsyClient {
    use GuardsPluginApiResponses;

    private ?PluginApiClient $api = null;

    public function __construct(
        private readonly PluginHttpFactory $http,
        private readonly EtsyConnection $connection,
        private readonly string $keystring,
        private readonly string $sharedSecret,
        private readonly string $baseUrl,
        private readonly float $requestInterval,
    ) {}

    /**
     * GET /v3/application/users/{user_id}/shops — Shop des verbundenen
     * Sellers (Ein-Shop-Semantik; liefert das Shop-Objekt oder null).
     *
     * @return array<string, mixed>|null
     */
    public function userShop(int $userId): ?array {
        $body = $this->guard(
            $this->api()->getResponse($this->url('/users/' . $userId . '/shops')),
            '/users/{user_id}/shops',
        );

        // Etsy liefert hier das Shop-Objekt direkt; defensiv auch den
        // {count, results}-Umschlag akzeptieren (Spec-Varianten).
        if (isset($body['shop_id'])) {
            return $body;
        }
        $results = $body['results'] ?? null;
        if (is_array($results) && is_array($results[0] ?? null)) {
            return $results[0];
        }

        return null;
    }

    /**
     * GET /v3/application/shops/{shop_id} — billigste OAuth-freie Probe für
     * den Healthcheck (wirft {@see EtsyApiException} bei Fehlerstatus).
     *
     * @return array<string, mixed>
     */
    public function shop(int $shopId): array {
        /** @var array<string, mixed> */
        return $this->guard(
            $this->api()->getResponse($this->url('/shops/' . $shopId)),
            '/shops/{shop_id}',
        );
    }

    /**
     * GET /v3/application/shops/{shop_id}/receipts — Sweep aufsteigend nach
     * `updated`, Aufholpunkt über `min_last_modified` (Epoch-Sekunden).
     * Receipts enthalten `transactions[]` embedded (W0 §4a).
     *
     * @return array{count: int, results: list<array<string, mixed>>}
     */
    public function receipts(int $shopId, int $minLastModified, int $limit, int $offset): array {
        $body = $this->guard(
            $this->api()->getResponse($this->url('/shops/' . $shopId . '/receipts'), [
                'min_last_modified' => $minLastModified,
                'sort_on' => 'updated',
                'sort_order' => 'asc',
                'limit' => $limit,
                'offset' => $offset,
            ]),
            '/shops/{shop_id}/receipts',
        );

        return $this->listed($body);
    }

    /**
     * GET /v3/application/shops/{shop_id}/receipts/{receipt_id} — Einzelabruf
     * (Webhook-Nachladen); null bei 404.
     *
     * @return array<string, mixed>|null
     */
    public function receipt(int $shopId, int $receiptId): ?array {
        $response = $this->api()->getResponse($this->url('/shops/' . $shopId . '/receipts/' . $receiptId));
        if ($response->status() === 404) {
            return null;
        }

        /** @var array<string, mixed> */
        return $this->guard($response, '/shops/{shop_id}/receipts/{receipt_id}');
    }

    /**
     * POST /v3/application/shops/{shop_id}/receipts/{receipt_id}/tracking —
     * Versand melden. Ohne tracking_code/carrier_name markiert Etsy nur
     * „versendet"; unbekannte Carrier laufen als `other` (W0 §5).
     *
     * @return array<string, mixed>
     */
    public function createReceiptShipment(int $shopId, int $receiptId, ?string $trackingCode, ?string $carrierName, ?string $noteToBuyer = null): array {
        $payload = array_filter([
            'tracking_code' => $trackingCode,
            'carrier_name' => $carrierName,
            'note_to_buyer' => $noteToBuyer,
        ], static fn(?string $value): bool => $value !== null && $value !== '');

        /** @var array<string, mixed> */
        return $this->guard(
            $this->api()->postJson($this->url('/shops/' . $shopId . '/receipts/' . $receiptId . '/tracking'), $payload),
            '/shops/{shop_id}/receipts/{receipt_id}/tracking',
        );
    }

    /**
     * GET /v3/application/shops/{shop_id}/payment-account/ledger-entries —
     * Pflicht-Zeitfenster (min/max_created, Epoch-Sekunden; W0 §6).
     *
     * @return array{count: int, results: list<array<string, mixed>>}
     */
    public function ledgerEntries(int $shopId, int $minCreated, int $maxCreated, int $limit, int $offset): array {
        $body = $this->guard(
            $this->api()->getResponse($this->url('/shops/' . $shopId . '/payment-account/ledger-entries'), [
                'min_created' => $minCreated,
                'max_created' => $maxCreated,
                'limit' => $limit,
                'offset' => $offset,
            ]),
            '/shops/{shop_id}/payment-account/ledger-entries',
        );

        return $this->listed($body);
    }

    /**
     * GET /v3/application/shops/{shop_id}/payments?payment_ids=… —
     * Batch-Abruf (Pflichtparameter payment_ids; W0 §6).
     *
     * @param  list<int>  $paymentIds
     * @return array{count: int, results: list<array<string, mixed>>}
     */
    public function payments(int $shopId, array $paymentIds): array {
        if ($paymentIds === []) {
            return ['count' => 0, 'results' => []];
        }

        $body = $this->guard(
            $this->api()->getResponse($this->url('/shops/' . $shopId . '/payments'), [
                'payment_ids' => implode(',', $paymentIds),
            ]),
            '/shops/{shop_id}/payments',
        );

        return $this->listed($body);
    }

    // ── Intern ──────────────────────────────────────────────────────────

    private function url(string $path): string {
        return $this->baseUrl . '/v3/application' . $path;
    }

    /**
     * `{count, results}`-Umschlag normalisieren.
     *
     * @param  array<mixed>  $body
     * @return array{count: int, results: list<array<string, mixed>>}
     */
    private function listed(array $body): array {
        $results = [];
        foreach (is_array($body['results'] ?? null) ? $body['results'] : [] as $row) {
            if (is_array($row)) {
                $results[] = $row;
            }
        }

        return ['count' => (int) ($body['count'] ?? count($results)), 'results' => $results];
    }

    /** Ein Exemplar je Client, damit das 0,2-s-Intervall zwischen Requests wirkt. */
    private function api(): PluginApiClient {
        if ($this->api === null) {
            $this->api = $this->http->client(EtsyPlugin::ID, $this->baseUrl, $this->requestInterval);
            $this->api->addDefaultHeader('x-api-key', $this->keystring . ':' . $this->sharedSecret);
            $this->api->setAuthentication(new OAuth2BearerAuthentication(
                new ConnectionTokenStore($this->connection),
                app(EtsyOAuthGrant::class)->grant(),
            ));
        }

        return $this->api;
    }

    /** @return class-string<\App\Plugins\Support\PluginApiException> */
    protected function apiExceptionClass(): string {
        return EtsyApiException::class;
    }

    protected function apiLabel(): string {
        return 'Etsy';
    }
}
