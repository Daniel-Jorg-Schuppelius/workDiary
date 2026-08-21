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

use App\Plugins\SevDesk\SevDeskPlugin;
use App\Plugins\Support\PluginHttpFactory;
use Illuminate\Http\Client\Response;
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
        return (array) $this->guard($this->authed('get', '/Tools/bookkeepingSystemVersion'), '/Tools/bookkeepingSystemVersion');
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
            $this->authed('get', '/Contact', ['query' => ['customerNumber' => $customerNumber, 'limit' => 10]]),
            '/Contact',
        );

        return $this->rows($body);
    }

    /**
     * POST /Contact — projizierten Kunden anlegen (Organisation + Kategorie).
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createContact(array $payload): array {
        $body = (array) $this->guard($this->authed('post', '/Contact', ['json' => $payload]), '/Contact');

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
            $this->authed('put', '/Contact/' . rawurlencode($contactId), ['json' => $payload]),
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
            $this->authed('get', '/Voucher', ['query' => [
                'offset' => $offset,
                'limit' => $limit,
                'ordering[id]' => 'DESC',
                'embed' => 'supplier',
            ]]),
            '/Voucher',
        );

        return $this->rows($body);
    }

    // ── Rechnungen ──────────────────────────────────────────────────────

    /**
     * POST /Invoice/Factory/saveInvoice — Rechnung + Positionen atomar.
     * Der Aufrufer setzt Status 50 (Entwurf) — sevDesk behält die
     * Rechnungshoheit; `enshrine` (irreversible Festschreibung) wird hier
     * bewusst NIE aufgerufen.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function saveInvoice(array $payload): array {
        $body = (array) $this->guard($this->authed('post', '/Invoice/Factory/saveInvoice', ['json' => $payload]), '/Invoice/Factory/saveInvoice');

        return $this->object($body);
    }

    /** @return array<int, mixed> GET /Invoice — jüngste zuerst (Reconciliation-Scan). */
    public function invoices(int $offset, int $limit): array {
        $body = (array) $this->guard(
            $this->authed('get', '/Invoice', ['query' => [
                'offset' => $offset,
                'limit' => $limit,
                // Jüngste zuerst, damit der Marker-Scan das kleinste Fenster braucht.
                'ordering[id]' => 'DESC',
            ]]),
            '/Invoice',
        );

        return $this->rows($body);
    }

    /** @return array<string, mixed> GET /Invoice/{id} — Statusrücklauf. */
    public function invoice(string $invoiceId): array {
        $body = (array) $this->guard($this->authed('get', '/Invoice/' . rawurlencode($invoiceId)), '/Invoice/{id}');

        return $this->object($body);
    }

    // ── Benutzer (contactPerson-Pflichtfeld der Rechnung) ───────────────

    /** @return array<string, mixed> Erster SevUser des Accounts (GET /SevUser). */
    public function firstSevUser(): array {
        $body = (array) $this->guard($this->authed('get', '/SevUser', ['query' => ['limit' => 1]]), '/SevUser');
        $rows = $this->rows($body);
        $first = $rows[0] ?? null;

        return is_array($first) ? $first : [];
    }

    // ── Intern ──────────────────────────────────────────────────────────

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

    /** @param array<string, mixed> $options */
    private function authed(string $method, string $path, array $options = []): Response {
        $options['headers'] = array_merge(
            (array) ($options['headers'] ?? []),
            // sevDesk-Vertrag: Token OHNE "Bearer "-Präfix.
            ['Authorization' => $this->apiKey],
        );

        return $this->http->client(SevDeskPlugin::ID, $this->baseUrl)
            ->requestResponse($method, $this->baseUrl . $path, $options);
    }

    /** @return class-string<\App\Plugins\Support\PluginApiException> */
    protected function apiExceptionClass(): string {
        return SevDeskApiException::class;
    }

    protected function apiLabel(): string {
        return 'sevDesk';
    }
}
