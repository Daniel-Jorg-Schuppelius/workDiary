<?php
/*
 * Created on   : Wed Nov 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RustDeskProClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\RemoteSupport\Api;

use APIToolkit\API\Authentication\BearerAuthentication;
use App\Plugins\RemoteSupport\Exceptions\RemoteProviderException;
use App\Plugins\RemoteSupport\Providers\{RemoteProvider, RemoteSession};
use App\Plugins\RemoteSupport\RemoteSupportPlugin;
use App\Plugins\Support\{PluginApiClient, PluginHttpFactory};
use App\Plugins\Support\TaskSync\{IncompleteRemoteReadException, OffsetPage, RemotePages};
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\StringHelper;

/**
 * RustDesk Server Pro: Verbindungsprotokoll der Web-Konsole über
 * `GET /api/audits/conn` mit API-Token (Einstellungen → Tokens, Recht
 * „Audit Log“). Die Anfrage ist nicht öffentlich dokumentiert; Pfad,
 * Parameter (`current`, `pageSize`, `created_at` als UTC `Y-m-d H:i:s`) und
 * Felder stammen aus der Konsole 1.8.7 (Belege: API-Dokumentabgleich
 * Fernwartung 2026-11-04). Weil die Semantik des `created_at`-Filters und die
 * Reihenfolge der Antwort nicht belegt sind, prüft der Client das Fenster
 * je Datensatz selbst und liest bis zur Seitenobergrenze.
 */
class RustDeskProClient implements RemoteProvider {
    public const string ID = 'rustdesk';

    public const string FLAVOR = 'pro';

    public const int PAGE_SIZE = 100;

    public const int MAX_PAGES = 200;

    /** `conn_type` der Konsole; 0 ist der Fernzugriff, alles andere trägt seine Art in der Notiz. */
    public const array CONN_TYPES = [1 => 'file', 2 => 'port', 3 => 'camera', 4 => 'terminal'];

    private ?PluginApiClient $api = null;

    public function __construct(
        private readonly ?string $baseUrl,
        private readonly ?string $apiToken,
    ) {}

    public function id(): string {
        return self::ID;
    }

    public function isConfigured(): bool {
        return $this->baseUrl !== null && $this->baseUrl !== ''
            && $this->apiToken !== null && $this->apiToken !== '';
    }

    public function probe(): void {
        $this->page(1, CarbonImmutable::now()->subDay(), probe: true);
    }

    public function fetchSessions(CarbonImmutable $from, CarbonImmutable $to): array {
        if (! $this->isConfigured()) {
            return [];
        }

        $sessions = [];
        try {
            foreach (RemotePages::offset(fn (int $page): OffsetPage => $this->page($page, $from), self::MAX_PAGES) as $record) {
                $session = $this->mapRecord((array) $record, $from, $to);
                if ($session !== null) {
                    $sessions[] = $session;
                }
            }
        } catch (IncompleteRemoteReadException $e) {
            throw RemoteProviderException::incomplete(self::ID, $e->reason, $sessions);
        }

        return $sessions;
    }

    /**
     * @return OffsetPage<array<mixed>>
     */
    private function page(int $page, CarbonImmutable $from, bool $probe = false): OffsetPage {
        $response = $this->api()->getResponse($this->url('/api/audits/conn'), [
            'current' => $page,
            'pageSize' => $probe ? 1 : self::PAGE_SIZE,
            'created_at' => $from->utc()->format('Y-m-d H:i:s'),
        ], ['timeout' => 20]);

        if (! $response->successful()) {
            throw RemoteProviderException::for(self::ID, sprintf('HTTP %d beim Lesen der Verbindungen (%s).', $response->status(), $this->baseUrl));
        }

        $data = $response->json('data');
        $total = $response->json('total');
        if (! is_array($data)) {
            throw RemoteProviderException::for(self::ID, 'Antwort ohne Feld „data“ — ist die Basis-URL die Web-Konsole von Server Pro?');
        }
        $items = array_values(array_filter($data, 'is_array'));
        $read = ($page - 1) * self::PAGE_SIZE + count($items);
        $hasMore = ! $probe && count($items) === self::PAGE_SIZE && (! is_numeric($total) || $read < (int) $total);

        return new OffsetPage($items, $hasMore, is_numeric($total) ? (int) $total : null);
    }

    /**
     * @param  array<mixed>  $record
     */
    private function mapRecord(array $record, CarbonImmutable $from, CarbonImmutable $to): ?RemoteSession {
        $remoteId = self::normalizeId((string) ($record['remote'] ?? ''));
        $start = $record['created_at'] ?? null;
        $end = $record['end_time'] ?? null;
        // Ohne Ende läuft die Verbindung noch; der nächste Lauf sieht sie abgeschlossen.
        if ($remoteId === '' || ! is_numeric($start) || ! is_numeric($end) || (int) $end === 0) {
            return null;
        }

        $startedAt = CarbonImmutable::createFromTimestampUTC((int) $start);
        if ($startedAt < $from || $startedAt > $to) {
            return null;
        }

        $alias = trim((string) ($record['remote_device_name'] ?? ''));
        $alias = ($alias !== '' && $alias !== $remoteId) ? $alias : null;

        $connType = $record['conn_type'] ?? null;
        $note = implode(' · ', array_filter([
            is_numeric($connType) && isset(self::CONN_TYPES[(int) $connType]) ? self::typeLabel((int) $connType) : null,
            trim((string) ($record['note'] ?? '')),
            trim((string) ($record['controller_user'] ?? '')),
        ], static fn (?string $part): bool => $part !== null && $part !== ''));

        return new RemoteSession(
            provider: self::ID,
            sessionId: (string) ($record['guid'] ?? ($remoteId . '|' . $start)),
            remoteId: $remoteId,
            startedAt: $startedAt,
            endedAt: CarbonImmutable::createFromTimestampUTC((int) $end),
            note: $note !== '' ? $note : null,
            alias: $alias,
        );
    }

    /** RustDesk-IDs werden mit Leerzeichen gruppiert angezeigt; gematcht wird ohne. */
    public static function normalizeId(string $id): string {
        $id = trim($id);

        return preg_match('/^[\d\s]+$/', $id) === 1 ? StringHelper::removeWhitespace($id) : $id;
    }

    public static function typeLabel(int $connType): string {
        return match (self::CONN_TYPES[$connType] ?? '') {
            'file' => (string) __('Dateiübertragung'),
            'port' => (string) __('Port-Weiterleitung'),
            'camera' => (string) __('Kamera'),
            'terminal' => (string) __('Terminal'),
            default => '',
        };
    }

    private function url(string $path): string {
        return rtrim((string) $this->baseUrl, '/') . $path;
    }

    private function api(): PluginApiClient {
        if ($this->api === null) {
            $this->api = app(PluginHttpFactory::class)->client(RemoteSupportPlugin::ID, rtrim((string) $this->baseUrl, '/'));
            $this->api->setAuthentication(new BearerAuthentication((string) $this->apiToken));
        }

        return $this->api;
    }
}
