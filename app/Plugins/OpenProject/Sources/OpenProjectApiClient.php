<?php
/*
 * Created on   : Mon Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenProjectApiClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\OpenProject\Sources;

use APIToolkit\API\Authentication\BasicAuthentication;
use App\Plugins\OpenProject\Exceptions\{OpenProjectApiException, OpenProjectRateLimitException};
use App\Plugins\Support\{PluginApiClient, PluginHttpFactory};
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;

/**
 * Client für die OpenProject API v3 (HAL+JSON, https://www.openproject.org/docs/api/).
 *
 * Authentifizierung per HTTP-Basic: Benutzername "apikey", das API-Token als
 * Passwort. Sammelkollektionen werden über die offset/pageSize-Paginierung
 * vollständig eingelesen (`_embedded.elements`, `total`). IDs werden aus den
 * HAL-`_links`-hrefs extrahiert.
 */
class OpenProjectApiClient {
    /** Elemente pro Seite bei Sammel-Abfragen. */
    public const PAGE_SIZE = 200;

    private ?PluginApiClient $api = null;

    public function __construct(
        private readonly ?string $apiToken,
        /** Normalisierte API-Wurzel inkl. /api/v3 (s. {@see \App\Plugins\OpenProject\OpenProjectConfig::normalizeBaseUrl()}). */
        private readonly ?string $baseUrl,
    ) {}

    public function isConfigured(): bool {
        return $this->apiToken !== null && $this->apiToken !== ''
            && $this->baseUrl !== null && $this->baseUrl !== '';
    }

    /** Health-Ping: /users/me liefert bei gültigem Token 200. */
    public function ping(): bool {
        if (! $this->isConfigured()) {
            return false;
        }

        return $this->api()->getResponse($this->baseUrl . '/users/me', [], ['timeout' => 8])->successful();
    }

    /**
     * Alle (für das Token sichtbaren) Projekte.
     *
     * @return array<int, OpenProjectProject>
     */
    public function fetchProjects(): array {
        $out = [];
        foreach ($this->collect('/projects') as $el) {
            $id = $this->stringId($el['id'] ?? null);
            $name = trim((string) ($el['name'] ?? ''));
            if ($id === null || $name === '') {
                continue;
            }
            $out[] = new OpenProjectProject(
                externalId: $id,
                name: $name,
                identifier: isset($el['identifier']) ? (string) $el['identifier'] : null,
                active: (bool) ($el['active'] ?? true),
                parentExternalId: $this->idFromHref($el['_links']['parent']['href'] ?? null),
            );
        }

        return $out;
    }

    /**
     * Alle Work Packages, optional auf ein Projekt eingeschränkt.
     *
     * @return array<int, OpenProjectWorkPackage>
     */
    public function fetchWorkPackages(?string $projectExternalId = null): array {
        $path = $projectExternalId !== null
            ? '/projects/' . rawurlencode($projectExternalId) . '/work_packages'
            : '/work_packages';

        $out = [];
        foreach ($this->collect($path) as $el) {
            $id = $this->stringId($el['id'] ?? null);
            $subject = trim((string) ($el['subject'] ?? ''));
            if ($id === null || $subject === '') {
                continue;
            }
            $links = (array) ($el['_links'] ?? []);
            $out[] = new OpenProjectWorkPackage(
                externalId: $id,
                subject: $subject,
                projectExternalId: $this->idFromHref($links['project']['href'] ?? null) ?? $projectExternalId,
                projectName: isset($links['project']['title']) ? (string) $links['project']['title'] : null,
                status: isset($links['status']['title']) ? (string) $links['status']['title'] : null,
                parentExternalId: $this->idFromHref($links['parent']['href'] ?? null),
            );
        }

        return $out;
    }

    /**
     * Benutzer der Instanz (nur für Administratoren mit E-Mail). Best-Effort:
     * fehlt die Berechtigung, kommt eine leere Liste zurück.
     *
     * @return array<int, OpenProjectUser>
     */
    public function fetchUsers(): array {
        $out = [];
        foreach ($this->collect('/users') as $el) {
            $id = $this->stringId($el['id'] ?? null);
            if ($id === null) {
                continue;
            }
            $name = trim((string) ($el['name'] ?? trim(($el['firstName'] ?? '') . ' ' . ($el['lastName'] ?? ''))));
            $email = isset($el['email']) && trim((string) $el['email']) !== '' ? trim((string) $el['email']) : null;
            $out[] = new OpenProjectUser($id, $name !== '' ? $name : $id, $email);
        }

        return $out;
    }

    /**
     * Zeiteinträge im Buchungsfenster [$from, $to], optional auf einen Benutzer
     * eingeschränkt.
     *
     * @return array<int, OpenProjectEntry>
     */
    public function fetchTimeEntries(CarbonImmutable $from, CarbonImmutable $to, ?string $userExternalId = null): array {
        $filters = [
            ['spentOn' => ['operator' => '<>d', 'values' => [$from->toDateString(), $to->toDateString()]]],
        ];
        if ($userExternalId !== null) {
            $filters[] = ['user' => ['operator' => '=', 'values' => [$userExternalId]]];
        }

        $out = [];
        foreach ($this->collect('/time_entries', ['filters' => json_encode($filters)]) as $el) {
            $entry = $this->mapTimeEntry((array) $el);
            if ($entry !== null) {
                $out[] = $entry;
            }
        }

        return $out;
    }

    /**
     * Legt einen Zeiteintrag in OpenProject an (Rückbuchung). Wirft bei Fehlern
     * eine {@see OpenProjectApiException} (bzw. {@see OpenProjectRateLimitException}
     * bei 429), damit der Aufrufer transiente von harten Fehlern unterscheidet.
     *
     * @return string  Die OpenProject-Zeiteintrags-ID des neuen Eintrags.
     */
    public function createTimeEntry(
        string $projectExternalId,
        ?string $workPackageExternalId,
        ?string $userExternalId,
        ?string $activityId,
        CarbonImmutable $spentOn,
        int $minutes,
        ?string $comment,
    ): string {
        $links = [
            'project' => ['href' => '/api/v3/projects/' . $projectExternalId],
        ];
        if ($workPackageExternalId !== null) {
            $links['workPackage'] = ['href' => '/api/v3/work_packages/' . $workPackageExternalId];
        }
        if ($userExternalId !== null) {
            $links['user'] = ['href' => '/api/v3/users/' . $userExternalId];
        }
        if ($activityId !== null) {
            $links['activity'] = ['href' => '/api/v3/time_entries/activities/' . $activityId];
        }

        $payload = [
            'spentOn' => $spentOn->toDateString(),
            'hours' => OpenProjectDuration::fromMinutes($minutes),
            'comment' => ['raw' => (string) $comment],
            '_links' => $links,
        ];

        $response = $this->api()->postJson($this->baseUrl . '/time_entries', $payload, ['timeout' => 20]);
        $this->guard($response, 'POST /time_entries');

        $id = $this->stringId($response->json('id'));
        if ($id === null) {
            throw new OpenProjectApiException(__('OpenProject lieferte keine ID für den angelegten Zeiteintrag.'));
        }

        return $id;
    }

    /**
     * Mappt ein HAL-Zeiteintrags-Element auf ein {@see OpenProjectEntry}-DTO.
     *
     * @param  array<string, mixed>  $el
     */
    private function mapTimeEntry(array $el): ?OpenProjectEntry {
        $id = $this->stringId($el['id'] ?? null);
        $spentOn = isset($el['spentOn']) ? trim((string) $el['spentOn']) : '';
        if ($id === null || $spentOn === '') {
            return null;
        }

        $links = (array) ($el['_links'] ?? []);

        // „entity“ (neuere API) bzw. „workPackage“ (deprecated) — nur Work Packages übernehmen.
        $entity = $links['entity'] ?? $links['workPackage'] ?? null;
        $entityHref = is_array($entity) ? ($entity['href'] ?? null) : null;
        $isWorkPackage = is_string($entityHref) && str_contains($entityHref, '/work_packages/');

        return new OpenProjectEntry(
            entryKey: 'openproject:te:' . $id,
            projectExternalId: $this->idFromHref($links['project']['href'] ?? null),
            projectName: isset($links['project']['title']) ? (string) $links['project']['title'] : null,
            workPackageExternalId: $isWorkPackage ? $this->idFromHref($entityHref) : null,
            workPackageSubject: $isWorkPackage && isset($entity['title']) ? (string) $entity['title'] : null,
            description: isset($el['comment']['raw']) && trim((string) $el['comment']['raw']) !== '' ? trim((string) $el['comment']['raw']) : null,
            spentOn: CarbonImmutable::parse($spentOn),
            minutes: OpenProjectDuration::toMinutes((string) ($el['hours'] ?? '')),
            userExternalId: $this->idFromHref($links['user']['href'] ?? null),
            userName: isset($links['user']['title']) ? (string) $links['user']['title'] : null,
        );
    }

    /**
     * Liest eine HAL-Kollektion vollständig über die offset/pageSize-Paginierung.
     *
     * @param  array<string, mixed>  $query
     * @return array<int, array<string, mixed>>
     */
    private function collect(string $path, array $query = []): array {
        if (! $this->isConfigured()) {
            return [];
        }

        $all = [];
        $offset = 1;

        do {
            $response = $this->api()
                ->getResponse($this->baseUrl . $path, array_merge($query, ['offset' => $offset, 'pageSize' => self::PAGE_SIZE]), ['timeout' => 30]);

            $this->guard($response, 'GET ' . $path);

            $elements = (array) ($response->json('_embedded.elements') ?? []);
            foreach ($elements as $el) {
                $all[] = (array) $el;
            }

            $total = (int) ($response->json('total') ?? 0);
            $offset++;
        } while ($elements !== [] && count($all) < $total);

        return $all;
    }

    /** Wirft bei Fehlerantworten — 429 separat, damit der Aufrufer Retry/Degraded unterscheidet. */
    private function guard(Response $response, string $context): void {
        if ($response->successful()) {
            return;
        }

        if ($response->status() === 429) {
            throw new OpenProjectRateLimitException(__('OpenProject-API rate-limited (:context).', ['context' => $context]));
        }

        $message = (string) ($response->json('message') ?? $response->reason());

        throw new OpenProjectApiException(__(':context fehlgeschlagen (HTTP :status): :message', [
            'context' => $context,
            'status' => $response->status(),
            'message' => $message,
        ]));
    }

    /** Extrahiert die abschließende numerische ID aus einem HAL-href (z. B. „/api/v3/projects/42“ → „42“). */
    private function idFromHref(?string $href): ?string {
        if (! is_string($href) || $href === '') {
            return null;
        }

        return preg_match('#/(\d+)(?:\?.*)?$#', $href, $m) ? $m[1] : null;
    }

    /** Normalisiert eine ID (int/string) auf einen nicht-leeren String oder null. */
    private function stringId(mixed $value): ?string {
        if (is_int($value)) {
            return (string) $value;
        }

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function api(): PluginApiClient {
        if ($this->api === null) {
            $this->api = app(PluginHttpFactory::class)->client('openproject', (string) $this->baseUrl);
            $this->api->setAuthentication(new BasicAuthentication('apikey', (string) $this->apiToken));
        }

        return $this->api;
    }
}
