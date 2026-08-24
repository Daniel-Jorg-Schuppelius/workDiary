<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EasybillClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Easybill\Api;

use App\Plugins\Easybill\EasybillPlugin;
use App\Plugins\Support\{PluginApiClient, PluginHttpFactory};
use Illuminate\Http\Client\Response;

/**
 * Typisierte Oberfläche der easybill-REST-API (MVP-431; Swagger-Fixture
 * tests/Fixtures/Plugins/Easybill/openapi.json, v1.99). HTTP über
 * {@see PluginApiClient} (php-api-toolkit: Retry/Backoff inkl. Retry-After).
 *
 * Auth: `Authorization: Bearer <api_key>`. Listen kommen als
 * `{page, pages, total, items: [...]}` und werden hier auf `items`
 * normalisiert. Das Request-Intervall ist tarifabhängig (PLUS 10/min,
 * BUSINESS 60/min) — EIN Client-Exemplar je EasybillClient, damit die
 * Toolkit-Drossel zwischen den Requests greift.
 */
class EasybillClient {
    // Gemeinsame guard()-Fehlerbehandlung (Vollaudit 2026-07, N33).
    use \App\Plugins\Support\GuardsPluginApiResponses;

    private ?PluginApiClient $api = null;

    public function __construct(
        private readonly PluginHttpFactory $http,
        private readonly string $apiKey,
        private readonly string $baseUrl,
        private readonly int $ratePerMinute,
    ) {}

    // ── Kunden ──────────────────────────────────────────────────────────

    /** @return array<int, mixed> GET /customers?number= — exakter Nummernfilter. */
    public function customersByNumber(string $number): array {
        $body = (array) $this->guard(
            $this->authed('get', '/customers', ['query' => ['number' => $number, 'limit' => 10]]),
            '/customers',
        );

        return $this->rows($body);
    }

    /**
     * POST /customers — projizierten Kunden anlegen.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createCustomer(array $payload): array {
        // 429/5xx-Retry gewollt (api-toolkit ≥2.9.2: POST-Retry ist Opt-in):
        // Dubletten fängt die Reconciliation über den external-id-Marker.
        return (array) $this->guard($this->authed('post', '/customers', ['json' => $payload, 'retry_non_idempotent' => true]), '/customers');
    }

    /**
     * PUT /customers/{id} — bestehenden Kunden aktualisieren (MVP-611).
     * Gelöscht wird im Fremdsystem nie; nur angelegt und aktualisiert.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function updateCustomer(string $customerId, array $payload): array {
        return (array) $this->guard(
            $this->authed('put', '/customers/' . rawurlencode($customerId), ['json' => $payload]),
            '/customers/{id}',
        );
    }

    // ── Belege ──────────────────────────────────────────────────────────

    /**
     * POST /documents (type INVOICE) — Beleg + Positionen atomar; entsteht
     * als Entwurf (`is_draft`), Nummer/Fertigstellung bleibt bei easybill:
     * `/documents/{id}/done` wird hier bewusst NIE aufgerufen.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createInvoiceDraft(array $payload): array {
        // 429/5xx-Retry gewollt (POST-Opt-in seit api-toolkit 2.9.2): der
        // Beleg entsteht als Entwurf mit external-id-Marker — ein doppelter
        // Draft würde von der Adoptions-Reconciliation eingefangen.
        return (array) $this->guard($this->authed('post', '/documents', ['json' => $payload, 'retry_non_idempotent' => true]), '/documents');
    }

    /** @return array<string, mixed> GET /documents/{id} — Status-/Nummernrücklauf. */
    public function document(string $documentId): array {
        return (array) $this->guard($this->authed('get', '/documents/' . rawurlencode($documentId)), '/documents/{id}');
    }

    /**
     * @param array<string, mixed> $query
     * @return array<int, mixed> GET /documents — normalisierte items[].
     */
    public function documents(array $query = []): array {
        $body = (array) $this->guard($this->authed('get', '/documents', ['query' => $query]), '/documents');

        return $this->rows($body);
    }

    /**
     * Reconciliation-Fenster: Rechnungen der letzten Tage (GET /documents
     * kennt keinen external_id-Filter — Vergleich macht der Aufrufer).
     *
     * @return array<int, mixed>
     */
    public function invoicesByDateRange(string $fromDate, string $toDate, int $page, int $limit): array {
        return $this->documents([
            'type' => 'INVOICE',
            // easybill-Filterkonvention: Zeitraum als "von,bis".
            'document_date' => $fromDate . ',' . $toDate,
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    /** Binärabruf GET /documents/{id}/pdf (application/pdf). */
    public function downloadPdf(string $documentId): ?string {
        $response = $this->authed('get', '/documents/' . rawurlencode($documentId) . '/pdf');
        if (! $response->successful()) {
            return null;
        }

        $body = (string) $response->body();

        return $body !== '' ? $body : null;
    }

    /**
     * Binärabruf GET /documents/{id}/download — liefert je file_format_config
     * PDF (ZUGFeRD) oder XML (XRechnung); Format meldet der Content-Type.
     *
     * @return array{content: string, mime: string}|null
     */
    public function downloadFile(string $documentId): ?array {
        $response = $this->authed('get', '/documents/' . rawurlencode($documentId) . '/download');
        if (! $response->successful()) {
            return null;
        }

        $body = (string) $response->body();
        if ($body === '') {
            return null;
        }

        return ['content' => $body, 'mime' => (string) ($response->header('Content-Type') ?: 'application/octet-stream')];
    }

    // ── Intern ──────────────────────────────────────────────────────────

    /**
     * easybill-Listenumschlag normalisieren ({..., items: [...]}).
     *
     * @param array<mixed> $body
     * @return array<int, mixed>
     */
    private function rows(array $body): array {
        $items = $body['items'] ?? null;

        return is_array($items) && array_is_list($items) ? $items : [];
    }

    /** @param array<string, mixed> $options */
    private function authed(string $method, string $path, array $options = []): Response {
        $options['headers'] = array_merge(
            (array) ($options['headers'] ?? []),
            ['Authorization' => 'Bearer ' . $this->apiKey],
        );

        return $this->api()->requestResponse($method, $this->baseUrl . $path, $options);
    }

    /** Ein Exemplar je Client, damit das Request-Intervall zwischen Requests wirkt. */
    private function api(): PluginApiClient {
        if ($this->api === null) {
            $this->api = $this->http->client(EasybillPlugin::ID, $this->baseUrl, 60.0 / max(1, $this->ratePerMinute));
        }

        return $this->api;
    }

    /** @return class-string<\App\Plugins\Support\PluginApiException> */
    protected function apiExceptionClass(): string {
        return EasybillApiException::class;
    }

    protected function apiLabel(): string {
        return 'easybill';
    }
}
