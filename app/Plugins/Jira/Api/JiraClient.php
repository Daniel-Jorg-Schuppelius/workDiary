<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JiraClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Jira\Api;

use APIToolkit\API\Pagination\CursorPage;
use App\Plugins\Jira\Exceptions\JiraApiException;
use App\Plugins\Jira\JiraPlugin;
use App\Plugins\Jira\Models\JiraConnection;
use App\Plugins\Support\{GuardsPluginApiResponses, PluginApiClient, PluginHttpFactory, SerializedBearerAuthentication};
use App\Plugins\Support\TaskSync\{IncompleteRemoteReadException, RemotePages};
use Generator;

/**
 * Lesezugriff auf Jira Cloud REST v3 einer gewählten Site: zugängliche
 * Ressourcen, Projekte, Enhanced Search mit `nextPageToken` und Einzelabruf.
 * Folge-URLs der Antworten werden nie abgerufen; Retry und `Retry-After`
 * übernimmt das Toolkit, der Token-Refresh ist je Verbindung gesperrt.
 */
class JiraClient {
    use GuardsPluginApiResponses;

    /** @var list<string> */
    public const ISSUE_FIELDS = ['summary', 'description', 'status', 'duedate', 'project'];

    private ?PluginApiClient $api = null;

    public function __construct(
        private readonly PluginHttpFactory $http,
        private readonly JiraConnection $connection,
        private readonly string $apiBase,
        private readonly int $pageSize,
        private readonly int $maxPages,
    ) {}

    /**
     * Sites, auf die der Consent Lesezugriff gibt (nur https auf *.atlassian.net).
     *
     * @return list<array{id: string, name: string, url: string}>
     */
    public function accessibleResources(): array {
        $sites = [];
        foreach ($this->guard($this->api()->getResponse('/oauth/token/accessible-resources'), '/oauth/token/accessible-resources') as $resource) {
            if (! is_array($resource) || ! is_string($resource['id'] ?? null) || ! JiraPlugin::isCloudId($resource['id'])) {
                continue;
            }
            $url = is_string($resource['url'] ?? null) ? rtrim($resource['url'], '/') : '';
            if (! JiraPlugin::isSiteUrl($url) || ! in_array('read:jira-work', (array) ($resource['scopes'] ?? []), true)) {
                continue;
            }
            $sites[] = ['id' => $resource['id'], 'name' => is_string($resource['name'] ?? null) ? $resource['name'] : $url, 'url' => $url];
        }

        return $sites;
    }

    /**
     * Projekte der Site (für den Zuordnungsdialog).
     *
     * @return list<array{id: string, key: string, name: string}>
     */
    public function projects(): array {
        $projects = [];
        foreach (RemotePages::all(function (?string $startAt): CursorPage {
            $body = $this->guard($this->api()->getResponse($this->path('/project/search'), [
                'startAt' => (int) ($startAt ?? 0),
                'maxResults' => 50,
                'orderBy' => 'name',
            ]), '/project/search');
            $values = $body['values'] ?? null;
            if (! is_array($values)) {
                throw new IncompleteRemoteReadException('invalid_response');
            }
            $next = ($body['isLast'] ?? true) === true ? null : (string) ((int) ($body['startAt'] ?? 0) + count($values));

            return new CursorPage($values, $values === [] ? null : $next);
        }, $this->maxPages) as $project) {
            if (is_array($project) && is_string($project['id'] ?? null) && ctype_digit($project['id'])) {
                $projects[] = ['id' => $project['id'], 'key' => (string) ($project['key'] ?? ''), 'name' => (string) ($project['name'] ?? $project['id'])];
            }
        }

        return $projects;
    }

    /**
     * Vorgänge eines Projekts über die Enhanced Search; `$condition` ist eine
     * feste, vom Dienst gebaute Zusatzbedingung — nie freies JQL.
     *
     * @return Generator<int, mixed>
     */
    public function issues(string $projectId, string $condition = ''): Generator {
        if (! ctype_digit($projectId)) {
            throw new IncompleteRemoteReadException('invalid_project');
        }
        $jql = 'project = ' . $projectId . ($condition !== '' ? ' AND ' . $condition : '') . ' ORDER BY created ASC';

        return RemotePages::all(function (?string $token) use ($jql): CursorPage {
            $body = $this->guard($this->api()->getResponse($this->path('/search/jql'), array_filter([
                'jql' => $jql,
                'fields' => implode(',', self::ISSUE_FIELDS),
                'maxResults' => $this->pageSize,
                'nextPageToken' => $token,
            ], static fn (mixed $v): bool => $v !== null)), '/search/jql');
            $issues = $body['issues'] ?? null;
            if (! is_array($issues)) {
                throw new IncompleteRemoteReadException('invalid_response');
            }
            $next = is_string($body['nextPageToken'] ?? null) && $body['nextPageToken'] !== '' ? $body['nextPageToken'] : null;
            $isLast = ($body['isLast'] ?? $next === null) === true;
            if (! $isLast && $next === null) {
                throw new IncompleteRemoteReadException('missing_cursor');
            }

            return new CursorPage($issues, $isLast ? null : $next);
        }, $this->maxPages);
    }

    /**
     * Einzelner Vorgang; null bei 404 (gelöscht oder ohne Leserecht).
     *
     * @return array<string, mixed>|null
     */
    public function issue(string $issueId): ?array {
        if (! ctype_digit($issueId)) {
            return null;
        }
        $response = $this->api()->getResponse($this->path('/issue/' . $issueId), ['fields' => implode(',', self::ISSUE_FIELDS)]);
        if ($response->status() === 404) {
            return null;
        }

        /** @var array<string, mixed> */
        return $this->guard($response, '/issue/{id}');
    }

    /** Billigste Leseprobe der gewählten Site (Healthcheck). */
    public function probe(): void {
        $this->guard($this->api()->getResponse($this->path('/project/search'), ['maxResults' => 1]), '/project/search');
    }

    private function path(string $endpoint): string {
        $cloudId = (string) $this->connection->source_id;
        if (! JiraPlugin::isCloudId($cloudId)) {
            throw new JiraApiException('Jira: keine gültige Site gewählt.', 0, $endpoint);
        }

        return '/ex/jira/' . $cloudId . '/rest/api/3' . $endpoint;
    }

    private function api(): PluginApiClient {
        if ($this->api === null) {
            $this->api = $this->http->client(JiraPlugin::ID, $this->apiBase);
            $this->api->setAuthentication(new SerializedBearerAuthentication($this->connection, app(JiraOAuthGrant::class)->grant()));
        }

        return $this->api;
    }

    /** @return class-string<\App\Plugins\Support\PluginApiException> */
    protected function apiExceptionClass(): string {
        return JiraApiException::class;
    }

    protected function apiLabel(): string {
        return 'Jira';
    }
}
