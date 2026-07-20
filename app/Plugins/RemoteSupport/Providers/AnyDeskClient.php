<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AnyDeskClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\RemoteSupport\Providers;

use App\Plugins\Support\{PluginApiClient, PluginHttpFactory};
use Carbon\CarbonImmutable;

/**
 * Client für die AnyDesk REST-API v1 (https://v1.api.anydesk.com:8081 —
 * die API lauscht nur auf Port 8081, Port 443 ist dort geschlossen).
 *
 * AnyDesk signiert jeden Request per HMAC-SHA1 mit dem API-Passwort der Lizenz;
 * der Lizenz-Schlüssel wandert mit in den Authorization-Header. Das Signieren
 * ist in {@see AnyDeskAuthentication} gekapselt. Verbindungen werden über
 * `/sessions` gelesen; gematcht wird das Asset über die kontrollierte
 * Client-ID (`to.cid`).
 */
class AnyDeskClient implements RemoteProvider {
    public const ID = 'anydesk';

    private ?PluginApiClient $api = null;

    public function __construct(
        private readonly ?string $licenseId,
        private readonly ?string $apiPassword,
        private readonly string $baseUrl = 'https://v1.api.anydesk.com:8081',
    ) {}

    public function id(): string {
        return self::ID;
    }

    public function isConfigured(): bool {
        return $this->licenseId !== null && $this->licenseId !== ''
            && $this->apiPassword !== null && $this->apiPassword !== '';
    }

    public function ping(): bool {
        if (! $this->isConfigured()) {
            return false;
        }

        return $this->api()
            ->getResponse($this->baseUrl . '/auth', [], ['timeout' => 5])
            ->successful();
    }

    public function fetchSessions(CarbonImmutable $from, CarbonImmutable $to): array {
        if (! $this->isConfigured()) {
            return [];
        }

        $resource = '/sessions';
        $query = [
            'from' => $from->getTimestamp(),
            'to' => $to->getTimestamp(),
        ];

        $response = $this->api()->getResponse($this->baseUrl . $resource, $query, ['timeout' => 15]);

        if (! $response->successful()) {
            return [];
        }

        $sessions = [];
        foreach ((array) ($response->json('sessions') ?? []) as $record) {
            $session = $this->mapRecord((array) $record);
            if ($session !== null) {
                $sessions[] = $session;
            }
        }

        return $sessions;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function mapRecord(array $record): ?RemoteSession {
        // Die kontrollierte (unbeaufsichtigte) Seite ist „to"; deren Client-ID
        // ist die am Asset hinterlegte Geräte-ID.
        $to = (array) ($record['to'] ?? []);
        $remoteId = (string) ($to['cid'] ?? $to['alias'] ?? '');
        $start = $record['start_time'] ?? null;
        $end = $record['end_time'] ?? null;
        if ($remoteId === '' || $start === null || $end === null) {
            return null;
        }

        // Der Alias ist der Klartext-Name des Geräts und lässt oft auf den
        // Rechnernamen schließen — nur übernehmen, wenn er sich von der ID unterscheidet.
        $alias = trim((string) ($to['alias'] ?? ''));
        $alias = ($alias !== '' && $alias !== $remoteId) ? $alias : null;

        $note = isset($record['comment']) ? trim((string) $record['comment']) : null;

        return new RemoteSession(
            provider: self::ID,
            sessionId: (string) ($record['sid'] ?? ($remoteId . '|' . $start)),
            remoteId: $remoteId,
            startedAt: CarbonImmutable::createFromTimestampUTC((int) $start),
            endedAt: CarbonImmutable::createFromTimestampUTC((int) $end),
            note: $note !== '' ? $note : null,
            alias: $alias,
        );
    }

    private function api(): PluginApiClient {
        if ($this->api === null) {
            $this->api = app(PluginHttpFactory::class)->client('remote-support', $this->baseUrl);
            $this->api->setAuthentication(new AnyDeskAuthentication((string) $this->licenseId, (string) $this->apiPassword));
        }

        return $this->api;
    }
}
