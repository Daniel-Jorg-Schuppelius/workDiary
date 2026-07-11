<?php
/*
 * Created on   : Fri Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrgaMaxClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\OrgaMax\Api;

use App\Models\OrgaMaxConnection;
use App\Plugins\OrgaMax\OrgaMaxPlugin;
use App\Plugins\Support\{PluginApiClient, PluginHttpFactory};
use Illuminate\Http\Client\Response;

/**
 * Typisierte Oberfläche der orgaMAX-OpenAPI (Feature 077, OAS 3.0.2).
 * HTTP über {@see PluginApiClient} (php-api-toolkit: Retry/Backoff inkl.
 * Retry-After, testbar via FakePluginHttp). Nur dokumentierte Endpunkte —
 * kein Screen-Scraping, keine ERP-Schnittstellen. Fehlerstatus wirft
 * {@see OrgaMaxApiException} ohne Secrets in der Message.
 */
class OrgaMaxClient {
    public function __construct(
        private readonly PluginHttpFactory $http,
        private readonly OrgaMaxTokenService $tokens,
        private readonly OrgaMaxConnection $connection,
        private readonly string $baseUrl,
    ) {}

    // ── Auth / Konto ────────────────────────────────────────────────────

    /**
     * Tauscht die ownershipId (`iid`) via HTTP Basic (API-Key + Secret) gegen
     * einen JWT-Bearer-Token (POST /auth/token).
     *
     * @return array{token: string, expires_at: \Illuminate\Support\Carbon|null}
     */
    public function exchangeToken(string $apiKey, string $apiSecret, string $ownershipId): array {
        $response = $this->client()->postJson($this->baseUrl . '/auth/token', ['ownershipId' => $ownershipId], [
            'auth' => [$apiKey, $apiSecret],
        ]);
        $body = (array) $this->guard($response, '/auth/token');

        $token = (string) ($body['token'] ?? $body['accessToken'] ?? $body['access_token'] ?? '');
        if ($token === '') {
            throw new OrgaMaxApiException($response->status(), 'orgaMAX /auth/token lieferte keinen Token.', '/auth/token');
        }

        return ['token' => $token, 'expires_at' => OrgaMaxTokenService::expiryFromJwt($token)];
    }

    /** @return array<string, mixed> GET /setting/account — erkannte Organisation. */
    public function accountSettings(): array {
        return (array) $this->guard($this->authed('get', '/setting/account'), '/setting/account');
    }

    // ── Stammdaten (paginiert, offset/limit) ────────────────────────────

    /** @return array<int, mixed> */
    public function customers(int $offset, int $limit): array {
        return $this->list('/customer', $offset, $limit);
    }

    /** @return array<int, mixed> */
    public function suppliers(int $offset, int $limit): array {
        return $this->list('/supplier', $offset, $limit);
    }

    /** @return array<int, mixed> */
    public function articles(int $offset, int $limit): array {
        return $this->list('/article', $offset, $limit);
    }

    // ── Aufträge / Rechnungen ───────────────────────────────────────────

    /** @return array<int, mixed> */
    public function orders(int $offset, int $limit): array {
        return $this->list('/order', $offset, $limit);
    }

    /**
     * POST /order/ — Auftrag anlegen.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createOrder(array $payload): array {
        return (array) $this->guard($this->authed('post', '/order/', ['json' => $payload]), '/order/');
    }

    /** @return array<string, mixed> POST /order/{id}/invoice — Auftrag → Rechnung. */
    public function orderToInvoice(string $orderId): array {
        return (array) $this->guard($this->authed('post', '/order/' . rawurlencode($orderId) . '/invoice'), '/order/{id}/invoice');
    }

    /** @return array<int, mixed> Dokumentierte Filterzustände: draft, locked, paid, dunned, cancelled. */
    public function invoices(int $offset, int $limit, ?string $status = null): array {
        $query = ['offset' => $offset, 'limit' => $limit];
        if ($status !== null) {
            $query['status'] = $status;
        }

        $body = (array) $this->guard($this->authed('get', '/invoice', ['query' => $query]), '/invoice');

        return $this->rows($body);
    }

    /** @return array<string, mixed> */
    public function invoice(string $invoiceId): array {
        return (array) $this->guard($this->authed('get', '/invoice/' . rawurlencode($invoiceId)), '/invoice/{id}');
    }

    /** PDF-Bytes über /invoice/document/{id}. */
    public function invoicePdf(string $invoiceId): string {
        $response = $this->authed('get', '/invoice/document/' . rawurlencode($invoiceId), [
            'headers' => ['Accept' => 'application/pdf'],
        ]);
        if (! $response->successful()) {
            throw $this->exception($response, '/invoice/document/{id}');
        }

        return (string) $response->body();
    }

    /**
     * PUT /invoice/{id}/lock — IRREVERSIBEL. Wird ausschließlich durch eine
     * ausdrücklich bestätigte Nutzeraktion aufgerufen, nie durch Polling,
     * Scheduler oder Retry (MVP-310).
     *
     * @return array<string, mixed>
     */
    public function lockInvoice(string $invoiceId): array {
        return (array) $this->guard($this->authed('put', '/invoice/' . rawurlencode($invoiceId) . '/lock'), '/invoice/{id}/lock');
    }

    /**
     * POST /invoice/{id}/send — Versand nach separater Bestätigung.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function sendInvoice(string $invoiceId, array $payload): array {
        return (array) $this->guard($this->authed('post', '/invoice/' . rawurlencode($invoiceId) . '/send', ['json' => $payload]), '/invoice/{id}/send');
    }

    /**
     * POST /invoice/{id}/payment — Zahlung melden (Dublettenprüfung beim Aufrufer).
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function addPayment(string $invoiceId, array $payload): array {
        return (array) $this->guard($this->authed('post', '/invoice/' . rawurlencode($invoiceId) . '/payment', ['json' => $payload]), '/invoice/{id}/payment');
    }

    // ── Intern ──────────────────────────────────────────────────────────

    /** @return array<int, mixed> */
    private function list(string $path, int $offset, int $limit): array {
        $body = (array) $this->guard(
            $this->authed('get', $path, ['query' => ['offset' => $offset, 'limit' => $limit]]),
            $path,
        );

        return $this->rows($body);
    }

    /**
     * Antwortformate tolerant normalisieren: nackte Liste oder Umschlag
     * (`data`/`items`/`rows`). Vertragsabweichungen klärt der Pilot (MVP-305).
     *
     * @param array<mixed> $body
     * @return array<int, mixed>
     */
    private function rows(array $body): array {
        if (array_is_list($body)) {
            return $body;
        }
        foreach (['data', 'items', 'rows'] as $key) {
            if (isset($body[$key]) && is_array($body[$key])) {
                return array_values($body[$key]);
            }
        }

        return [];
    }

    /** @param array<string, mixed> $options */
    private function authed(string $method, string $path, array $options = []): Response {
        $token = $this->tokens->validTokenFor($this->connection);
        $options['headers'] = array_merge(
            (array) ($options['headers'] ?? []),
            ['Authorization' => 'Bearer ' . $token],
        );

        return $this->client()->requestResponse($method, $this->baseUrl . $path, $options);
    }

    private function client(): PluginApiClient {
        return $this->http->client(OrgaMaxPlugin::ID, $this->baseUrl);
    }

    /** @return array<mixed> */
    private function guard(Response $response, string $endpoint): array {
        if (! $response->successful()) {
            throw $this->exception($response, $endpoint);
        }

        return (array) ($response->json() ?? []);
    }

    private function exception(Response $response, string $endpoint): OrgaMaxApiException {
        return new OrgaMaxApiException(
            $response->status(),
            sprintf('orgaMAX %s: HTTP %d %s', $endpoint, $response->status(), mb_substr((string) $response->body(), 0, 300)),
            $endpoint,
        );
    }
}
