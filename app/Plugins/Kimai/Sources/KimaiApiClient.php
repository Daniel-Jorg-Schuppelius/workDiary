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
use App\Plugins\Support\{PluginApiClient, PluginHttpFactory};
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
class KimaiApiClient {
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
