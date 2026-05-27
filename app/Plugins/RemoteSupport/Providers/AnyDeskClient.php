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

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

/**
 * Client für die AnyDesk REST-API v1 (https://v1.api.anydesk.com).
 *
 * AnyDesk signiert jeden Request per HMAC-SHA1 mit dem API-Passwort der Lizenz;
 * der Lizenz-Schlüssel wandert mit in den Authorization-Header. Das Signieren
 * ist in {@see authHeaders()} gekapselt. Verbindungen werden über `/sessions`
 * gelesen; gematcht wird das Asset über die kontrollierte Client-ID (`to.cid`).
 */
class AnyDeskClient implements RemoteProvider {
    public const ID = 'anydesk';

    public function __construct(
        private readonly ?string $licenseId,
        private readonly ?string $apiPassword,
        private readonly string $baseUrl = 'https://v1.api.anydesk.com',
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

        return Http::withHeaders($this->authHeaders('GET', '/auth'))
            ->acceptJson()
            ->timeout(5)
            ->get($this->baseUrl . '/auth')
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

        $response = Http::withHeaders($this->authHeaders('GET', $resource))
            ->acceptJson()
            ->timeout(15)
            ->get($this->baseUrl . $resource, $query);

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

        $note = isset($record['comment']) ? trim((string) $record['comment']) : null;

        return new RemoteSession(
            provider: self::ID,
            sessionId: (string) ($record['sid'] ?? ($remoteId . '|' . $start)),
            remoteId: $remoteId,
            startedAt: CarbonImmutable::createFromTimestampUTC((int) $start),
            endedAt: CarbonImmutable::createFromTimestampUTC((int) $end),
            note: $note !== '' ? $note : null,
        );
    }

    /**
     * Baut die für die AnyDesk-API erforderlichen Authentifizierungs-Header.
     * Signatur = base64(HMAC-SHA1(request-string, api_password)), wobei der
     * request-string Methode, Ressource, Timestamp und ein Einmal-Token bindet.
     *
     * @return array<string, string>
     */
    private function authHeaders(string $method, string $resource): array {
        $token = base64_encode(random_bytes(16));
        $timestamp = (string) now()->getTimestamp();
        $contentHash = base64_encode(sha1('', true));

        $requestString = implode("\n", [
            strtoupper($method),
            $resource,
            $contentHash,
            $timestamp,
            $token,
        ]);

        $signature = base64_encode(
            hash_hmac('sha1', $requestString, (string) $this->apiPassword, true),
        );

        return [
            'Authorization' => sprintf('AD %s:%s:%s:%s', $this->licenseId, $token, $timestamp, $signature),
        ];
    }
}
