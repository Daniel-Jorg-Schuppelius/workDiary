<?php
/*
 * Created on   : Wed Nov 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RustDeskApiServerClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\RemoteSupport\Api;

use APIToolkit\API\Authentication\ApiKeyAuthentication;
use App\Plugins\RemoteSupport\Exceptions\RemoteProviderException;
use App\Plugins\RemoteSupport\Providers\{RemoteProvider, RemoteSession};
use App\Plugins\RemoteSupport\RemoteSupportPlugin;
use App\Plugins\Support\{PluginApiClient, PluginHttpFactory};
use App\Support\Tz;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\StringHelper;
use Throwable;

/**
 * Open-Source-API-Server für RustDesk (lejianwen/rustdesk-api): der
 * kostenlose RustDesk-Server speichert keine Verbindungen, der API-Server
 * nimmt die Audit-Meldungen der Clients entgegen. Gelesen wird die
 * Admin-Liste `GET /api/admin/audit_conn/list` (nach ID absteigend) nach
 * einer Anmeldung je Lauf (`POST /api/admin/login`, Kopf `api-token`).
 * Belegt nur aus dem Quelltext (Hauptzweig 2026-11-04): `created_at` ist
 * Serverzeit ohne Zone, `close_time` Unixzeit, 0 = noch offen; eine Notiz
 * führt das Modell nicht.
 */
class RustDeskApiServerClient implements RemoteProvider {
    public const ID = RustDeskProClient::ID;

    public const FLAVOR = 'api_server';

    public const PAGE_SIZE = 100;

    public const MAX_PAGES = 200;

    private ?PluginApiClient $api = null;

    public function __construct(
        private readonly ?string $baseUrl,
        private readonly ?string $username,
        private readonly ?string $password,
        private readonly ?string $timezone = null,
    ) {}

    public function id(): string {
        return self::ID;
    }

    public function isConfigured(): bool {
        return $this->baseUrl !== null && $this->baseUrl !== ''
            && $this->username !== null && $this->username !== ''
            && $this->password !== null && $this->password !== '';
    }

    public function probe(): void {
        $this->login();
    }

    public function fetchSessions(CarbonImmutable $from, CarbonImmutable $to): array {
        if (! $this->isConfigured()) {
            return [];
        }

        $this->api()->setAuthentication(new ApiKeyAuthentication($this->login(), 'api-token'));
        $timezone = $this->timezone !== null && Tz::isValid($this->timezone) ? $this->timezone : Tz::current();
        $sessions = [];

        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            $response = $this->api()->getResponse($this->url('/api/admin/audit_conn/list'), [
                'page' => $page,
                'page_size' => self::PAGE_SIZE,
            ], ['timeout' => 20]);
            $payload = $this->envelope($response, 'Verbindungsliste');

            $list = $payload['list'] ?? null;
            if (! is_array($list)) {
                throw RemoteProviderException::for(self::ID, 'Verbindungsliste ohne Feld „list“.');
            }

            $reachedWindowStart = false;
            foreach ($list as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $startedAt = $this->parseCreatedAt($row['created_at'] ?? null, $timezone);
                if ($startedAt === null) {
                    continue;
                }
                // Liste nach ID absteigend: vor dem Fenster ist alles Weitere älter.
                if ($startedAt < $from) {
                    $reachedWindowStart = true;
                    break;
                }
                if ($startedAt > $to) {
                    continue;
                }
                $session = $this->mapRow($row, $startedAt);
                if ($session !== null) {
                    $sessions[] = $session;
                }
            }

            $total = $payload['total'] ?? null;
            $read = ($page - 1) * self::PAGE_SIZE + count($list);
            if ($reachedWindowStart || count($list) < self::PAGE_SIZE || (is_numeric($total) && $read >= (int) $total)) {
                return $sessions;
            }
        }

        throw RemoteProviderException::incomplete(self::ID, 'page_limit', $sessions);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function mapRow(array $row, CarbonImmutable $startedAt): ?RemoteSession {
        $remoteId = RustDeskProClient::normalizeId((string) ($row['peer_id'] ?? ''));
        $close = $row['close_time'] ?? null;
        if ($remoteId === '' || ! is_numeric($close) || (int) $close === 0) {
            return null;
        }

        $type = $row['type'] ?? null;
        $fromName = trim((string) ($row['from_name'] ?? ''));
        $note = implode(' · ', array_filter([
            is_numeric($type) && isset(RustDeskProClient::CONN_TYPES[(int) $type]) ? RustDeskProClient::typeLabel((int) $type) : null,
            $fromName !== '' ? (string) __('von :name', ['name' => $fromName]) : null,
        ]));

        return new RemoteSession(
            provider: self::ID,
            sessionId: (string) ($row['id'] ?? ($remoteId . '|' . $startedAt->getTimestamp())),
            remoteId: $remoteId,
            startedAt: $startedAt,
            endedAt: CarbonImmutable::createFromTimestampUTC((int) $close),
            note: $note !== '' ? $note : null,
        );
    }

    /** `created_at` kommt als `Y-m-d H:i:s` in der Serverzeit des API-Servers. */
    private function parseCreatedAt(mixed $value, string $timezone): ?CarbonImmutable {
        $value = StringHelper::trimToNull(is_scalar($value) ? (string) $value : null);
        if ($value === null) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value, $timezone)->utc();
        } catch (Throwable) {
            return null;
        }
    }

    private function login(): string {
        $response = $this->api()->postJson($this->url('/api/admin/login'), [
            'username' => (string) $this->username,
            'password' => (string) $this->password,
            'platform' => 'workDiary',
        ], ['timeout' => 15, 'retry_non_idempotent' => true]);
        $payload = $this->envelope($response, 'Anmeldung');

        $token = $payload['token'] ?? null;
        if (! is_string($token) || $token === '') {
            throw RemoteProviderException::for(self::ID, 'Anmeldung ohne Token beantwortet.');
        }

        return $token;
    }

    /**
     * Antworthülle des API-Servers: `code` 0 ist Erfolg, sonst steht die
     * Ursache in `message` (etwa „NeedLogin“, „UsernameOrPasswordError“).
     *
     * @return array<string, mixed>
     */
    private function envelope(\Illuminate\Http\Client\Response $response, string $what): array {
        if (! $response->successful()) {
            throw RemoteProviderException::for(self::ID, sprintf('HTTP %d bei %s (%s).', $response->status(), $what, $this->baseUrl));
        }
        $code = $response->json('code');
        if ((int) $code !== 0) {
            throw RemoteProviderException::for(self::ID, sprintf('%s abgelehnt: %s (Code %s).', $what, (string) ($response->json('message') ?? '-'), (string) $code));
        }
        $data = $response->json('data');

        return is_array($data) ? $data : [];
    }

    private function url(string $path): string {
        return rtrim((string) $this->baseUrl, '/') . $path;
    }

    private function api(): PluginApiClient {
        if ($this->api === null) {
            // Die Anmeldung läuft ohne Zugangsdaten; danach trägt jede Anfrage das Token im Kopf `api-token`.
            $this->api = app(PluginHttpFactory::class)->client(RemoteSupportPlugin::ID, rtrim((string) $this->baseUrl, '/'));
        }

        return $this->api;
    }
}
