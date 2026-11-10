<?php
/*
 * Created on   : Sat Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SevDeskClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\SevDesk\Api;

use APIToolkit\API\Authentication\ApiKeyAuthentication;
use App\Plugins\SevDesk\Exceptions\SevDeskApiException;
use App\Plugins\SevDesk\SevDeskPlugin;
use App\Plugins\Support\{PluginApiClient, PluginHttpFactory};
use Illuminate\Support\Facades\Cache;

/**
 * Typisierte Oberfläche der sevDesk-REST-API (MVP-125, api.sevdesk.de).
 * HTTP über {@see \App\Plugins\Support\PluginApiClient} (php-api-toolkit: Retry/Backoff inkl.
 * Retry-After — sevDesk dokumentiert keine Rate-Limits, daher defensiv).
 *
 * Auth: API-Token im `Authorization`-Header OHNE Bearer-Präfix
 * (sevDesk-Vertrag). Pagination `limit` 1–1000, sonst 400. Antworten sind in
 * `{"objects": ...}` eingeschlagen und werden hier normalisiert.
 *
 * Buchhaltungs-Version („Update 2.0" ist Kontologik je Account, keine neue
 * API): {@see bookkeepingVersion()} cacht GET /Tools/bookkeepingSystemVersion
 * je Mandant — 2.0 verlangt `taxRule` statt `taxType` am Beleg.
 */
class SevDeskClient {
    // Gemeinsame guard()-Fehlerbehandlung (Vollaudit 2026-07, N33).
    use \App\Plugins\Support\GuardsPluginApiResponses;

    private ?PluginApiClient $api = null;

    public function __construct(
        private readonly PluginHttpFactory $http,
        private readonly string $apiKey,
        private readonly string $baseUrl,
        private readonly int $organizationId,
    ) {}

    // ── Version (je Mandant gecacht) ────────────────────────────────────

    /**
     * Normalisierte Buchhaltungs-Version des Mandanten: '1.0' oder '2.0'.
     * Gecacht je Organisation; `$fresh = true` (Healthcheck) erneuert den
     * Cache mit einer frischen API-Probe.
     */
    public function bookkeepingVersion(bool $fresh = false): string {
        $key = self::versionCacheKey($this->organizationId);
        if ($fresh) {
            Cache::forget($key);
        }

        $ttl = max(60, (int) config('plugins.sevdesk.version_cache_ttl', 21600));

        return (string) Cache::remember($key, $ttl, function (): string {
            return self::normalizeVersion($this->bookkeepingSystemVersion());
        });
    }

    public static function versionCacheKey(int $organizationId): string {
        return 'sevdesk:bookkeeping_version:' . $organizationId;
    }

    /** @return array<string, mixed> GET /Tools/bookkeepingSystemVersion (ungecacht). */
    public function bookkeepingSystemVersion(): array {
        return (array) $this->guard($this->api()->requestResponse('get', $this->baseUrl . '/Tools/bookkeepingSystemVersion'), '/Tools/bookkeepingSystemVersion');
    }

    /**
     * Versionsantwort tolerant auf '1.0'/'2.0' abbilden — das Feld heißt je
     * nach Stand `version` bzw. `bookkeepingSystemVersion` und trägt Werte
     * wie "2.0" oder "Version 2.0".
     *
     * @param array<string, mixed> $body
     */
    public static function normalizeVersion(array $body): string {
        $objects = is_array($body['objects'] ?? null) ? $body['objects'] : $body;
        $raw = (string) ($objects['version'] ?? $objects['bookkeepingSystemVersion'] ?? '');

        return preg_match('/2(\.\d+)?/', $raw) === 1 ? '2.0' : '1.0';
    }

    // ── Kontakte ────────────────────────────────────────────────────────

    /** @return array<int, mixed> GET /Contact?customerNumber= — exakte Nummer. */
    public function contactsByCustomerNumber(string $customerNumber): array {
        $body = (array) $this->guard(
            $this->api()->requestResponse('get', $this->baseUrl . '/Contact', ['query' => ['customerNumber' => $customerNumber, 'limit' => 10]]),
            '/Contact',
        );

        return $this->rows($body);
    }

    /** @return array<int, mixed> GET /Contact?name= — Organisationen und Personen mit diesem Namen (Phase 152, MVP-1226). */
    public function contactsByName(string $name): array {
        $body = (array) $this->guard(
            $this->api()->requestResponse('get', $this->baseUrl . '/Contact', ['query' => ['name' => $name, 'depth' => 1, 'limit' => 25]]),
            '/Contact',
        );

        return $this->rows($body);
    }

    /** @return array<int, mixed> GET /Contact?parent[id]= — Personen einer Organisation (sevDesk-Filter „contacts with id as a parent organisation“). */
    public function contactsByParent(string $contactId): array {
        $body = (array) $this->guard(
            $this->api()->requestResponse('get', $this->baseUrl . '/Contact', ['query' => ['parent[id]' => $contactId, 'parent[objectName]' => 'Contact', 'depth' => 1, 'limit' => 25]]),
            '/Contact',
        );

        return $this->rows($body);
    }

    /**
     * Eine Seite aller Kontakte (`GET /Contact`, Kontakt-Rückweg MVP-1502):
     * `depth=1` liefert Organisationen und Personen, `countAll=true` die
     * Gesamtzahl im Feld `total` (Abschnitt „Pagination“ der Doku).
     *
     * @return array{rows: list<array<string, mixed>>, total: ?int}
     */
    public function contacts(int $offset, int $limit): array {
        return $this->page('/Contact', ['depth' => 1, 'offset' => $offset, 'limit' => $limit]);
    }

    /**
     * Eine Seite aller Kontaktadressen (`GET /ContactAddress`, operationId
     * getContactAddresses, MVP-1529): `Model_ContactAddressResponse` mit
     * `contact.id`, `street`, `zip`, `city`, `country` und `category`.
     *
     * @return array{rows: list<array<string, mixed>>, total: ?int}
     */
    public function contactAddresses(int $offset, int $limit): array {
        return $this->page('/ContactAddress', ['offset' => $offset, 'limit' => $limit]);
    }

    /**
     * Eine Seite aller Kommunikationswege (`GET /CommunicationWay`, operationId
     * getCommunicationWays, MVP-1529): `Model_CommunicationWayResponse` mit
     * `contact.id`, `type` (EMAIL, PHONE, WEB, MOBILE), `value`, `key` und `main`.
     *
     * @return array{rows: list<array<string, mixed>>, total: ?int}
     */
    public function communicationWays(int $offset, int $limit): array {
        return $this->page('/CommunicationWay', ['offset' => $offset, 'limit' => $limit]);
    }

    /** GET /Contact/Factory/getNextCustomerNumber — nächste freie Kundennummer, wenn sevDesk vergibt (MVP-1131). */
    public function nextCustomerNumber(): string {
        $body = $this->guard(
            $this->api()->requestResponse('get', $this->baseUrl . '/Contact/Factory/getNextCustomerNumber'),
            '/Contact/Factory/getNextCustomerNumber',
        );
        $number = $body['objects'] ?? '';

        return is_scalar($number) ? trim((string) $number) : '';
    }

    /**
     * POST /Contact — projizierten Kunden anlegen (Organisation + Kategorie).
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createContact(array $payload): array {
        $body = (array) $this->guard($this->api()->requestResponse('post', $this->baseUrl . '/Contact', ['json' => $payload]), '/Contact');

        return $this->object($body);
    }

    /**
     * PUT /Contact/{id} — bestehenden Kontakt aktualisieren (MVP-611).
     * Gelöscht wird im Fremdsystem nie.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function updateContact(string $contactId, array $payload): array {
        $body = (array) $this->guard(
            $this->api()->requestResponse('put', $this->baseUrl . '/Contact/' . rawurlencode($contactId), ['json' => $payload]),
            '/Contact/{id}',
        );

        return $this->object($body);
    }

    /**
     * GET /Voucher — Belege, die direkt in sevDesk entstanden sind
     * (Kassenbon, Lieferantenrechnung). Jüngste zuerst (MVP-611).
     *
     * @return array<int, mixed>
     */
    public function vouchers(int $offset, int $limit): array {
        $body = (array) $this->guard(
            $this->api()->requestResponse('get', $this->baseUrl . '/Voucher', ['query' => [
                'offset' => $offset,
                'limit' => $limit,
                'ordering[id]' => 'DESC',
                'embed' => 'supplier',
            ]]),
            '/Voucher',
        );

        return $this->rows($body);
    }

    /**
     * `GET /Voucher` mit Filtern (Rechnungseingang, MVP-1507): belegt sind
     * `descriptionLike`, `creditDebit`, `startDate`/`endDate` (Zeitstempel) und
     * `contact[id]` mit `contact[objectName]=Contact` (operationId getVouchers).
     *
     * @param  array<string, scalar>  $filter
     * @return list<array<string, mixed>>
     */
    public function findVouchers(array $filter): array {
        $body = (array) $this->guard(
            $this->api()->requestResponse('get', $this->baseUrl . '/Voucher', ['query' => $filter + ['limit' => 50, 'embed' => 'supplier']]),
            '/Voucher',
        );

        return array_values(array_filter($this->rows($body), 'is_array'));
    }

    /**
     * `POST /Voucher/Factory/uploadTempFile` (operationId voucherUploadFile):
     * Datei als Formularfeld `file`, Antwort `objects.filename` für
     * `saveVoucher`. XML und PDF mit eingebetteter XML (ZUGFeRD) nimmt sevDesk
     * nur ab Buchhaltung 2.0 an (API-News 2024-11-27).
     */
    public function uploadVoucherFile(string $content, string $filename): string {
        $body = $this->object($this->guard(
            $this->api()->requestResponse('post', $this->baseUrl . '/Voucher/Factory/uploadTempFile', ['multipart' => [
                ['name' => 'file', 'contents' => $content, 'filename' => $filename],
            ]]),
            '/Voucher/Factory/uploadTempFile',
        ));
        $name = trim((string) ($body['filename'] ?? ''));
        if ($name === '') {
            throw new SevDeskApiException('sevDesk uploadTempFile returned no filename.', 0, '/Voucher/Factory/uploadTempFile');
        }

        return $name;
    }

    /**
     * `POST /Voucher/Factory/saveVoucher` (operationId voucherFactorySaveVoucher):
     * Beleg mit Positionen und angehängter Datei; Status nur 50 (Entwurf) oder
     * 100 (offen). Antwort `objects.voucher`.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function saveVoucher(array $payload): array {
        return $this->object((array) $this->guard(
            $this->api()->requestResponse('post', $this->baseUrl . '/Voucher/Factory/saveVoucher', ['json' => $payload]),
            '/Voucher/Factory/saveVoucher',
        ));
    }

    /**
     * POST /ContactAddress — Adresse als **eigenes Objekt** am Kontakt
     * (MVP-731). sevDesk führt Adressen nicht als Felder des Kontakts,
     * sondern als eigene Ressource mit eigenem Endpunkt.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createContactAddress(array $payload): array {
        $body = (array) $this->guard($this->api()->requestResponse('post', $this->baseUrl . '/ContactAddress', ['json' => $payload]), '/ContactAddress');

        return $this->object($body);
    }

    /**
     * PUT /ContactAddress/{id} — bestehende Adresse aktualisieren; gelöscht
     * wird im Fremdsystem nie.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function updateContactAddress(string $addressId, array $payload): array {
        $body = (array) $this->guard(
            $this->api()->requestResponse('put', $this->baseUrl . '/ContactAddress/' . rawurlencode($addressId), ['json' => $payload]),
            '/ContactAddress/{id}',
        );

        return $this->object($body);
    }

    /**
     * POST /CommunicationWay — E-Mail/Telefon am Kontakt (MVP-731), ebenfalls
     * eigene Ressource mit eigenem Endpunkt.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createCommunicationWay(array $payload): array {
        $body = (array) $this->guard($this->api()->requestResponse('post', $this->baseUrl . '/CommunicationWay', ['json' => $payload]), '/CommunicationWay');

        return $this->object($body);
    }

    /**
     * PUT /CommunicationWay/{id} — bestehenden Kommunikationsweg aktualisieren.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function updateCommunicationWay(string $wayId, array $payload): array {
        $body = (array) $this->guard(
            $this->api()->requestResponse('put', $this->baseUrl . '/CommunicationWay/' . rawurlencode($wayId), ['json' => $payload]),
            '/CommunicationWay/{id}',
        );

        return $this->object($body);
    }

    // ── Rechnungen ──────────────────────────────────────────────────────

    /**
     * POST /Invoice/Factory/saveInvoice — Rechnung + Positionen atomar.
     * Der Aufrufer setzt Status 100 (Entwurf) — sevDesk behält die
     * Rechnungshoheit; `enshrine` (irreversible Festschreibung) wird hier
     * bewusst NIE aufgerufen.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function saveInvoice(array $payload): array {
        $body = (array) $this->guard($this->api()->requestResponse('post', $this->baseUrl . '/Invoice/Factory/saveInvoice', ['json' => $payload]), '/Invoice/Factory/saveInvoice');

        return $this->object($body);
    }

    /** @return array<int, mixed> GET /Invoice — jüngste zuerst (Reconciliation-Scan). */
    public function invoices(int $offset, int $limit): array {
        $body = (array) $this->guard(
            $this->api()->requestResponse('get', $this->baseUrl . '/Invoice', ['query' => [
                'offset' => $offset,
                'limit' => $limit,
                // Jüngste zuerst, damit der Marker-Scan das kleinste Fenster braucht.
                'ordering[id]' => 'DESC',
            ]]),
            '/Invoice',
        );

        return $this->rows($body);
    }

    /**
     * GET /SevSequence/Factory/getByType — nächste Rechnungsnummer, so wie
     * sevDesk sie vergeben würde (MVP-1166). Null, wenn sevDesk keine meldet;
     * dann vergibt sevDesk sie beim Speichern selbst.
     */
    public function nextInvoiceNumber(): ?string {
        $body = (array) $this->guard(
            $this->api()->requestResponse('get', $this->baseUrl . '/SevSequence/Factory/getByType', ['query' => ['objectType' => 'Invoice', 'type' => 'RE']]),
            '/SevSequence/Factory/getByType',
        );
        $objects = $body['objects'] ?? null;
        if (is_scalar($objects)) {
            return trim((string) $objects) ?: null;
        }
        $sequence = is_array($objects) ? $this->object(['objects' => $objects]) : [];
        $next = trim((string) ($sequence['nextSequence'] ?? ''));
        if ($next === '') {
            return null;
        }
        $format = (string) ($sequence['format'] ?? '');
        // Das Format trägt die laufende Nummer als Platzhalter, etwa „RE-[%nr]“.
        $number = preg_replace('/\[%?nr%?\]|%nr%?/i', $next, $format, 1, $count);

        return $count > 0 && is_string($number) ? $number : $next;
    }

    /** PUT /Invoice/{id}/sendBy — als versendet markieren; sevDesk führt die Rechnung danach als offen (MVP-1166). */
    public function markInvoiceSent(string $invoiceId): void {
        $this->guard(
            $this->api()->requestResponse('put', $this->baseUrl . '/Invoice/' . rawurlencode($invoiceId) . '/sendBy', ['json' => ['sendType' => 'VPDF', 'sendDraft' => false]]),
            '/Invoice/{id}/sendBy',
        );
    }

    /**
     * POST /Invoice/{id}/cancelInvoice — Stornorechnung zur Rechnung (MVP-1167).
     *
     * @return array<string, mixed>
     */
    public function cancelInvoice(string $invoiceId): array {
        $body = (array) $this->guard($this->api()->requestResponse('post', $this->baseUrl . '/Invoice/' . rawurlencode($invoiceId) . '/cancelInvoice'), '/Invoice/{id}/cancelInvoice');

        return $this->object($body);
    }

    /** @return array<string, mixed> GET /Invoice/{id} — Statusrücklauf. */
    /**
     * `GET /Invoice/{invoiceId}/getPositions` (Feature 185, MVP-1425) — alle
     * Positionen einer Rechnung, Artikel eingebettet (`embed=part`).
     *
     * @return list<array<string, mixed>>
     */
    public function invoicePositions(string $invoiceId): array {
        $body = (array) $this->guard(
            $this->api()->requestResponse('get', $this->baseUrl . '/Invoice/' . rawurlencode($invoiceId) . '/getPositions', ['query' => ['embed' => 'part,unity']]),
            '/Invoice/{id}/getPositions',
        );
        $objects = $body['objects'] ?? [];

        return is_array($objects) ? array_values(array_filter($objects, 'is_array')) : [];
    }

    /** @return array<string, mixed> */
    public function invoice(string $invoiceId): array {
        $body = (array) $this->guard($this->api()->requestResponse('get', $this->baseUrl . '/Invoice/' . rawurlencode($invoiceId)), '/Invoice/{id}');

        return $this->object($body);
    }

    /**
     * `GET /Invoice/{id}/getXml` (Feature 196): XRechnung einer E-Rechnung, im
     * Feld `objects` als Zeichenkette. Null, wenn sevDesk keine liefert (400).
     */
    public function invoiceXml(string $invoiceId): ?string {
        $response = $this->api()->requestResponse('get', $this->baseUrl . '/Invoice/' . rawurlencode($invoiceId) . '/getXml');
        if ($response->status() === 400) {
            return null;
        }
        $xml = $this->guard($response, '/Invoice/{id}/getXml')['objects'] ?? null;

        return is_string($xml) && trim($xml) !== '' ? $xml : null;
    }

    /**
     * `GET /Invoice/{id}/getPdf` (Feature 196) mit `preventSendBy=true`: ohne
     * den Schalter kann sevDesk den Abruf als Versand verbuchen. Bei einer
     * E-Rechnung (`propertyIsEInvoice`) trägt das PDF die XML (ZUGFeRD).
     *
     * @return array{content: string, mime: string, filename: string}|null
     */
    public function invoicePdf(string $invoiceId): ?array {
        return $this->documentPdf('Invoice', $invoiceId);
    }

    /**
     * `GET /CreditNote/{id}/getPdf` (operationId creditNoteGetPdf, MVP-1504):
     * gleiche Antwort und Schalter wie bei der Rechnung.
     *
     * @return array{content: string, mime: string, filename: string}|null
     */
    public function creditNotePdf(string $creditNoteId): ?array {
        return $this->documentPdf('CreditNote', $creditNoteId);
    }

    /** @return array{content: string, mime: string, filename: string}|null */
    private function documentPdf(string $resource, string $id): ?array {
        $response = $this->api()->requestResponse('get', $this->baseUrl . '/' . $resource . '/' . rawurlencode($id) . '/getPdf', ['query' => ['download' => 'true', 'preventSendBy' => 'true']]);
        if ($response->status() === 400) {
            return null;
        }
        $body = $this->object($this->guard($response, '/' . $resource . '/{id}/getPdf'));
        $content = (string) ($body['content'] ?? '');
        if ($content === '') {
            return null;
        }
        if (($body['base64encoded'] ?? true) !== false) {
            $content = (string) base64_decode($content, true);
        }

        return $content === '' ? null : [
            'content' => $content,
            'mime' => (string) ($body['mimeType'] ?? 'application/pdf'),
            'filename' => (string) ($body['filename'] ?? ''),
        ];
    }

    // ── Gutschriften ────────────────────────────────────────────────────

    /**
     * `GET /CreditNote` (operationId getCreditNotes, MVP-1504): `offset`/`limit`
     * laut Abschnitt „Pagination“ der Doku, neueste Nummer zuerst über den
     * belegten Filter `orderByCreditNoteNumber`.
     *
     * @return array<int, mixed>
     */
    public function creditNotes(int $offset, int $limit): array {
        $body = (array) $this->guard(
            $this->api()->requestResponse('get', $this->baseUrl . '/CreditNote', ['query' => [
                'offset' => $offset,
                'limit' => $limit,
                'orderByCreditNoteNumber' => 'DESC',
            ]]),
            '/CreditNote',
        );

        return $this->rows($body);
    }

    /**
     * `GET /CreditNotePos` (operationId getcreditNotePositions): Positionen einer
     * Gutschrift; `creditNote[objectName]` trägt laut Doku den Wert `creditNote`.
     *
     * @return list<array<string, mixed>>
     */
    public function creditNotePositions(string $creditNoteId): array {
        $body = (array) $this->guard(
            $this->api()->requestResponse('get', $this->baseUrl . '/CreditNotePos', ['query' => [
                'creditNote[id]' => $creditNoteId,
                'creditNote[objectName]' => 'creditNote',
                'embed' => 'part,unity',
                'limit' => 1000,
            ]]),
            '/CreditNotePos',
        );

        return array_values(array_filter($this->rows($body), 'is_array'));
    }

    // ── Artikel ─────────────────────────────────────────────────────────

    /**
     * Eine Seite des Artikelstamms (`GET /Part`, Feature 185, gap-09):
     * `Model_Part` mit `id`, `name`, `partNumber`, `text`, `priceNet`/`price`,
     * `taxRate`, `status` (50 inaktiv, 100 aktiv), `stockEnabled`; die Einheit
     * kommt über `embed=unity` (`unity.name`), `countAll=true` liefert `total`.
     *
     * @return array{rows: list<array<string, mixed>>, total: ?int}
     */
    public function parts(int $offset, int $limit): array {
        return $this->page('/Part', ['offset' => $offset, 'limit' => $limit, 'embed' => 'unity', 'ordering[id]' => 'ASC']);
    }

    // ── Benutzer (contactPerson-Pflichtfeld der Rechnung) ───────────────

    /** @return array<string, mixed> Erster SevUser des Accounts (GET /SevUser). */
    public function firstSevUser(): array {
        $body = (array) $this->guard($this->api()->requestResponse('get', $this->baseUrl . '/SevUser', ['query' => ['limit' => 1]]), '/SevUser');
        $rows = $this->rows($body);
        $first = $rows[0] ?? null;

        return is_array($first) ? $first : [];
    }

    // ── Intern ──────────────────────────────────────────────────────────

    /**
     * Eine Listenseite mit Gesamtzahl: `countAll=true` liefert sie im Feld
     * `total` (Abschnitt „Pagination“ der Doku).
     *
     * @param  array<string, mixed>  $query
     * @return array{rows: list<array<string, mixed>>, total: ?int}
     */
    private function page(string $resource, array $query): array {
        $body = (array) $this->guard(
            $this->api()->requestResponse('get', $this->baseUrl . $resource, ['query' => $query + ['countAll' => 'true']]),
            $resource,
        );
        $total = $body['total'] ?? null;

        return [
            'rows' => array_values(array_filter($this->rows($body), 'is_array')),
            'total' => is_numeric($total) ? (int) $total : null,
        ];
    }

    /**
     * sevDesk-Umschlag normalisieren: Listen kommen als `objects`-Array.
     *
     * @param array<mixed> $body
     * @return array<int, mixed>
     */
    private function rows(array $body): array {
        $objects = $body['objects'] ?? $body;
        if (is_array($objects) && array_is_list($objects)) {
            return $objects;
        }

        return [];
    }

    /**
     * Einzelobjekt aus dem `objects`-Umschlag lösen (Factory-Antworten
     * liefern verschachtelte Objekte, z. B. `objects.invoice`).
     *
     * @param array<mixed> $body
     * @return array<string, mixed>
     */
    private function object(array $body): array {
        $objects = $body['objects'] ?? $body;
        if (is_array($objects) && array_is_list($objects)) {
            $objects = $objects[0] ?? [];
        }

        return is_array($objects) ? $objects : [];
    }

    /** Ein Exemplar je Client; die Anmeldung hängt daran. */
    private function api(): PluginApiClient {
        if ($this->api === null) {
            $this->api = $this->http->client(SevDeskPlugin::ID, $this->baseUrl);
            // sevDesk-Vertrag: Token OHNE "Bearer "-Präfix.
            $this->api->setAuthentication(new ApiKeyAuthentication($this->apiKey, 'Authorization'));
        }

        return $this->api;
    }

    /** @return class-string<\App\Plugins\Support\PluginApiException> */
    protected function apiExceptionClass(): string {
        return SevDeskApiException::class;
    }

    protected function apiLabel(): string {
        return 'sevDesk';
    }
}
