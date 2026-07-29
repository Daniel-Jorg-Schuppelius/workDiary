<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KimaiApiClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Kimai\Sources;

use APIToolkit\API\Authentication\BearerAuthentication;
use App\Plugins\Kimai\Exceptions\KimaiApiException;
use App\Plugins\Support\{PluginApiClient, PluginHttpFactory, RemoteTimeWriter, StartStopFingerprint};
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;

/**
 * Kimai-2.x-API-Client auf dem `php-api-toolkit`-Fundament
 * ({@see PluginApiClient}: Retry/Backoff inkl. `Retry-After`, injizierbares
 * Guzzle): Bearer-Token-Auth (`Authorization: Bearer <api-token>`), Timesheets
 * seitenweise (`page`/`size`, max. 500) mit `full=true`, damit Projekt, Kunde
 * und Tätigkeit als eingebettete Objekte mitkommen (keine Zusatz-Lookups).
 * Tests ersetzen die {@see PluginHttpFactory} durch
 * {@see \Tests\Support\FakePluginHttp}.
 */
class KimaiApiClient implements RemoteTimeWriter {
    use StartStopFingerprint;

    public const PAGE_SIZE = 500;

    private ?PluginApiClient $api = null;

    public function __construct(
        private readonly ?string $apiToken,
        private readonly ?string $baseUrl,
    ) {}

    public function isConfigured(): bool {
        return $this->apiToken !== null && trim($this->apiToken) !== ''
            && $this->baseUrl !== null && trim($this->baseUrl) !== '';
    }

    /** Erreichbarkeits-/Auth-Check über den Ping-Endpunkt. */
    public function ping(): bool {
        return $this->api()->getResponse($this->url('/api/ping'))->successful();
    }

    /**
     * Alle Timesheets im Fenster, seitenweise vollständig ausgelesen
     * (`orderBy=id&order=ASC` für stabile Pagination). `begin`/`end` filtern
     * auf die Startzeit (HTML5-datetime-local, Kimai-Konvention); `user=all`
     * braucht das Recht `view_other_timesheet` — ohne `allUsers` liefert die
     * API nur den Token-Benutzer.
     *
     * @return list<array<string, mixed>>
     */
    public function getTimesheets(?CarbonImmutable $begin = null, ?CarbonImmutable $end = null, bool $allUsers = true): array {
        $rows = [];
        $page = 1;

        do {
            $query = [
                'full' => 'true',
                'size' => self::PAGE_SIZE,
                'page' => $page,
                'orderBy' => 'id',
                'order' => 'ASC',
            ];
            if ($allUsers) {
                $query['user'] = 'all';
            }
            if ($begin !== null) {
                $query['begin'] = $begin->format('Y-m-d\TH:i:s');
            }
            if ($end !== null) {
                $query['end'] = $end->format('Y-m-d\TH:i:s');
            }

            $response = $this->api()->getResponse($this->url('/api/timesheets'), $query);

            // Kimai rendert eine 404 für Seiten jenseits der letzten — reguläres Ende.
            if ($response->status() === 404 && $page > 1) {
                break;
            }
            $this->assertOk($response, '/api/timesheets');

            /** @var list<array<string, mixed>> $batch */
            $batch = array_values((array) $response->json());
            $rows = array_merge($rows, $batch);
            $page++;
        } while (count($batch) === self::PAGE_SIZE);

        return $rows;
    }

    /**
     * Legt ein Timesheet an (Export-Rückkanal). Erwartete Felder:
     * begin/end (HTML5-datetime-local), project (ID), activity (ID),
     * description, billable.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createTimesheet(array $payload): array {
        $response = $this->api()->postJson($this->url('/api/timesheets'), $payload);
        $this->assertOk($response, '/api/timesheets');

        /** @var array<string, mixed> */
        return (array) $response->json();
    }

    /**
     * Aktueller Kimai-Stand (Rückrichtung: Konflikterkennung und -anzeige).
     *
     * @param  array<string, mixed>  $context
     * @return array{description: ?string, date: ?CarbonImmutable, started_at: ?CarbonImmutable, ended_at: ?CarbonImmutable, minutes: int, billable: bool}|null
     */
    public function fetchRemoteState(string $externalId, array $context): ?array {
        if (! $this->isConfigured() || ! is_numeric($externalId)) {
            return null;
        }

        $response = $this->api()->getResponse($this->url('/api/timesheets/' . (int) $externalId));
        if (! $response->successful()) {
            return null;
        }

        $row = $response->json();
        if (! is_array($row) || ! isset($row['begin'], $row['end']) || ! is_string($row['begin']) || ! is_string($row['end'])) {
            return null; // laufender Eintrag (kein Ende) oder unerwartete Form
        }

        $begin = CarbonImmutable::parse($row['begin']);
        $end = CarbonImmutable::parse($row['end']);

        return [
            'description' => isset($row['description']) ? (string) $row['description'] : null,
            'date' => $begin,
            'started_at' => $begin,
            'ended_at' => $end,
            'minutes' => (int) round($begin->diffInSeconds($end) / 60),
            'billable' => (bool) ($row['billable'] ?? false),
        ];
    }

    /**
     * Überträgt den lokalen Stand (PATCH). Kimai erwartet Zeiten als
     * HTML5-datetime-local in der Benutzer-Zeitzone — wie beim Lesen.
     *
     * @param  array{description: ?string, date: ?CarbonImmutable, started_at: ?CarbonImmutable, ended_at: ?CarbonImmutable, minutes: int, billable: bool}  $entry
     * @param  array<string, mixed>  $context
     */
    public function pushEntryUpdate(string $externalId, array $entry, array $context): bool {
        if (! $this->isConfigured() || ! is_numeric($externalId) || $entry['started_at'] === null || $entry['ended_at'] === null) {
            return false;
        }

        return $this->api()->requestResponse('patch', $this->url('/api/timesheets/' . (int) $externalId), [
            'json' => [
                'begin' => $entry['started_at']->format('Y-m-d\TH:i:s'),
                'end' => $entry['ended_at']->format('Y-m-d\TH:i:s'),
                'description' => (string) $entry['description'],
                'billable' => $entry['billable'],
            ],
        ])->successful();
    }

    /**
     * Löscht das Timesheet. Ein bereits gelöschtes (404) gilt als erledigt.
     *
     * @param  array<string, mixed>  $context
     */
    public function pushEntryDeletion(string $externalId, array $context): bool {
        if (! $this->isConfigured() || ! is_numeric($externalId)) {
            return false;
        }

        $response = $this->api()->deleteResponse($this->url('/api/timesheets/' . (int) $externalId));

        return $response->successful() || $response->status() === 404;
    }

    private function url(string $path): string {
        return rtrim((string) $this->baseUrl, '/') . $path;
    }

    private function assertOk(Response $response, string $context): void {
        if ($response->successful()) {
            return;
        }

        $detail = (string) ($response->json('message') ?? $response->body());

        throw new KimaiApiException(
            sprintf('Kimai-API %s: HTTP %d — %s', $context, $response->status(), mb_substr($detail, 0, 300)),
            $response->status(),
        );
    }

    private function api(): PluginApiClient {
        if ($this->api === null) {
            $this->api = app(PluginHttpFactory::class)->client('kimai', rtrim((string) $this->baseUrl, '/'));
            $this->api->setAuthentication(new BearerAuthentication((string) $this->apiToken));
        }

        return $this->api;
    }
}
