<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RedmineClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Redmine\Api;

use APIToolkit\API\Authentication\ApiKeyAuthentication;
use APIToolkit\API\Pagination\CursorPage;
use App\Plugins\Redmine\Exceptions\RedmineApiException;
use App\Plugins\Redmine\RedminePlugin;
use App\Plugins\Support\{GuardsPluginApiResponses, PluginApiClient, PluginHttpFactory};
use App\Plugins\Support\TaskSync\{IncompleteRemoteReadException, RemotePages};
use CommonToolkit\Helper\Data\PersonNameHelper;
use Generator;

/**
 * Lesezugriff auf die Redmine-REST-API: Konto, Projekte, Issues, Status und
 * Zeiteinträge. Listen über `offset`/`limit` bis `total_count`; ob ein Status
 * geschlossen ist, kommt aus `is_closed` am Issue oder — bei älteren Instanzen —
 * aus der Statusliste.
 */
class RedmineClient {
    use GuardsPluginApiResponses;

    private ?PluginApiClient $api = null;

    /** @var array<string, bool>|null */
    private ?array $closedStatuses = null;

    public function __construct(
        private readonly PluginHttpFactory $http,
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly int $pageSize,
        private readonly int $maxPages,
    ) {}

    /** @return array{id: ?string, name: ?string} */
    public function currentUser(): array {
        $user = $this->guard($this->api()->getResponse($this->url('/users/current.json')), '/users/current.json')['user'] ?? null;
        $user = is_array($user) ? $user : [];
        $name = PersonNameHelper::join(['first_name' => $user['firstname'] ?? null, 'last_name' => $user['lastname'] ?? null]);

        return [
            'id' => is_scalar($user['id'] ?? null) ? (string) $user['id'] : null,
            'name' => $name !== '' ? $name : (is_string($user['login'] ?? null) ? $user['login'] : null),
        ];
    }

    /** @return list<array{id: string, key: string, name: string}> */
    public function projects(): array {
        $projects = [];
        foreach ($this->pages('/projects.json', 'projects', []) as $project) {
            if (is_array($project) && is_scalar($project['id'] ?? null) && ctype_digit((string) $project['id'])) {
                $projects[] = ['id' => (string) $project['id'], 'key' => (string) ($project['identifier'] ?? ''), 'name' => (string) ($project['name'] ?? $project['id'])];
            }
        }

        return $projects;
    }

    /**
     * Issues genau dieses Projekts (ohne Unterprojekte); `$since` als ISO-8601 in UTC.
     *
     * @return Generator<int, mixed>
     */
    public function issues(string $projectId, bool $openOnly, ?string $since): Generator {
        if (! ctype_digit($projectId)) {
            throw new IncompleteRemoteReadException('invalid_project');
        }

        return $this->pages('/issues.json', 'issues', array_filter([
            'project_id' => $projectId,
            'subproject_id' => '!*',
            'status_id' => $openOnly ? 'open' : '*',
            'updated_on' => $since !== null ? '>=' . $since : null,
            'sort' => 'id',
        ], static fn (?string $v): bool => $v !== null));
    }

    /**
     * Einzelnes Issue; null bei 404 oder ohne Leserecht.
     *
     * @return array<string, mixed>|null
     */
    public function issue(string $issueId): ?array {
        if (! ctype_digit($issueId)) {
            return null;
        }
        $response = $this->api()->getResponse($this->url('/issues/' . $issueId . '.json'));
        if (in_array($response->status(), [403, 404], true)) {
            return null;
        }
        $issue = $this->guard($response, '/issues/{id}.json')['issue'] ?? null;

        return is_array($issue) ? $issue : null;
    }

    /** Ist der Status geschlossen? null = unbekannter Status. */
    public function isClosedStatus(mixed $status): ?bool {
        if (! is_array($status)) {
            return null;
        }
        if (is_bool($status['is_closed'] ?? null)) {
            return $status['is_closed'];
        }
        if ($this->closedStatuses === null) {
            $this->closedStatuses = [];
            foreach ((array) ($this->guard($this->api()->getResponse($this->url('/issue_statuses.json')), '/issue_statuses.json')['issue_statuses'] ?? []) as $row) {
                if (is_array($row) && is_scalar($row['id'] ?? null)) {
                    $this->closedStatuses[(string) $row['id']] = (bool) ($row['is_closed'] ?? false);
                }
            }
        }

        return is_scalar($status['id'] ?? null) ? ($this->closedStatuses[(string) $status['id']] ?? null) : null;
    }

    /**
     * Zeiteinträge eines Projekts (ohne Unterprojekte) im Datumsfenster.
     *
     * @return Generator<int, mixed>
     */
    public function timeEntries(string $projectId, string $from, string $to): Generator {
        if (! ctype_digit($projectId)) {
            throw new IncompleteRemoteReadException('invalid_project');
        }

        // Ohne `subproject_id=!*` liefert Redmine (Voreinstellung display_subprojects_issues) Unterprojekte mit.
        return $this->pages('/time_entries.json', 'time_entries', ['project_id' => $projectId, 'subproject_id' => '!*', 'from' => $from, 'to' => $to]);
    }

    /** Leseprobe der Instanz (Healthcheck). */
    public function probe(): void {
        $this->currentUser();
    }

    /**
     * @param  array<string, string>  $query
     * @return Generator<int, mixed>
     */
    private function pages(string $path, string $key, array $query): Generator {
        $total = null;

        return RemotePages::all(function (?string $offset) use ($path, $key, $query, &$total): CursorPage {
            $current = (int) ($offset ?? 0);
            $body = $this->guard($this->api()->getResponse($this->url($path), $query + ['offset' => $current, 'limit' => $this->pageSize]), $path);
            $items = $body[$key] ?? null;
            if (! is_array($items) || ! array_is_list($items) || ! is_numeric($body['total_count'] ?? null)) {
                throw new IncompleteRemoteReadException('invalid_response');
            }
            // Schrumpft die Gesamtzahl beim Blättern, rücken Einträge über die Seitengrenze — kein vollständiges Lesen.
            if ($total !== null && (int) $body['total_count'] < $total) {
                throw new IncompleteRemoteReadException('listing_changed');
            }
            $total = (int) $body['total_count'];
            $next = $current + count($items);

            return new CursorPage($items, $items !== [] && $next < $total ? (string) $next : null);
        }, $this->maxPages);
    }

    private function url(string $path): string {
        return $this->baseUrl . $path;
    }

    private function api(): PluginApiClient {
        if ($this->api === null) {
            $this->api = $this->http->client(RedminePlugin::ID, $this->baseUrl);
            $this->api->setAuthentication(new ApiKeyAuthentication($this->apiKey, 'X-Redmine-API-Key'));
        }

        return $this->api;
    }

    /** @return class-string<\App\Plugins\Support\PluginApiException> */
    protected function apiExceptionClass(): string {
        return RedmineApiException::class;
    }

    protected function apiLabel(): string {
        return 'Redmine';
    }
}
