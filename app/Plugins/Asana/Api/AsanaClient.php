<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AsanaClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Asana\Api;

use APIToolkit\API\Pagination\CursorPage;
use App\Plugins\Asana\AsanaPlugin;
use App\Plugins\Asana\Exceptions\AsanaApiException;
use App\Plugins\Asana\Models\AsanaConnection;
use App\Plugins\Support\{GuardsPluginApiResponses, PluginApiClient, PluginHttpFactory, SerializedBearerAuthentication};
use App\Plugins\Support\TaskSync\{IncompleteRemoteReadException, RemotePages};
use Generator;

/**
 * Lesezugriff auf die Asana-API 1.0 des gewählten Workspaces: Konto, Projekte,
 * Aufgaben je Projekt (`opt_fields`, `next_page.offset`, `completed_since`)
 * und Einzelabruf. `next_page.uri` wird nie abgerufen; ein abgelaufener
 * Offset (400 auf einer Folgeseite) startet das Projekt einmal neu.
 */
class AsanaClient {
    use GuardsPluginApiResponses;

    public const TASK_FIELDS = 'gid,name,notes,completed,due_on,due_at,permalink_url,workspace.gid,memberships.project.gid';

    private ?PluginApiClient $api = null;

    public function __construct(
        private readonly PluginHttpFactory $http,
        private readonly AsanaConnection $connection,
        private readonly string $apiBase,
        private readonly int $pageSize,
        private readonly int $maxPages,
    ) {}

    /**
     * Konto und Workspaces des Consents.
     *
     * @return array{gid: ?string, name: ?string, workspaces: list<array{id: string, name: string, url: null}>}
     */
    public function me(): array {
        $data = $this->guard($this->api()->getResponse($this->url('/users/me'), ['opt_fields' => 'gid,name,workspaces.gid,workspaces.name']), '/users/me')['data'] ?? null;
        $data = is_array($data) ? $data : [];
        $workspaces = [];
        foreach ((array) ($data['workspaces'] ?? []) as $workspace) {
            if (is_array($workspace) && AsanaPlugin::isGid($workspace['gid'] ?? null)) {
                $workspaces[] = ['id' => (string) $workspace['gid'], 'name' => (string) ($workspace['name'] ?? $workspace['gid']), 'url' => null];
            }
        }

        return [
            'gid' => AsanaPlugin::isGid($data['gid'] ?? null) ? (string) $data['gid'] : null,
            'name' => is_string($data['name'] ?? null) ? $data['name'] : null,
            'workspaces' => $workspaces,
        ];
    }

    /**
     * Nicht archivierte Projekte des Workspaces.
     *
     * @return list<array{id: string, key: string, name: string}>
     */
    public function projects(): array {
        $workspace = $this->workspace();
        $projects = [];
        foreach (RemotePages::all(fn (?string $offset): CursorPage => $this->page('/projects', [
            'workspace' => $workspace,
            'archived' => 'false',
            'opt_fields' => 'gid,name',
        ], $offset), $this->maxPages) as $project) {
            if (is_array($project) && AsanaPlugin::isGid($project['gid'] ?? null)) {
                $projects[] = ['id' => (string) $project['gid'], 'key' => '', 'name' => (string) ($project['name'] ?? $project['gid'])];
            }
        }

        return $projects;
    }

    /**
     * Aufgaben eines Projekts; `$completedSince` begrenzt erledigte Aufgaben
     * (unerledigte kommen immer). Liefert nur Aufgaben des gewählten Workspaces.
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function projectTasks(string $projectGid, string $completedSince): Generator {
        if (! AsanaPlugin::isGid($projectGid)) {
            throw new IncompleteRemoteReadException('invalid_project');
        }
        $workspace = $this->workspace();
        for ($attempt = 0; ; $attempt++) {
            try {
                foreach (RemotePages::all(fn (?string $offset): CursorPage => $this->page('/projects/' . $projectGid . '/tasks', [
                    'completed_since' => $completedSince,
                    'opt_fields' => self::TASK_FIELDS,
                ], $offset), $this->maxPages) as $task) {
                    if (is_array($task) && (string) data_get($task, 'workspace.gid') === $workspace) {
                        yield $task;
                    }
                }

                return;
            } catch (AsanaApiException $e) {
                if ($e->errorCode !== 'offset_rejected' || $attempt > 0) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Einzelne Aufgabe des gewählten Workspaces; null bei 404 oder fremdem Workspace.
     *
     * @return array<string, mixed>|null
     */
    public function task(string $taskGid): ?array {
        if (! AsanaPlugin::isGid($taskGid)) {
            return null;
        }
        $response = $this->api()->getResponse($this->url('/tasks/' . $taskGid), ['opt_fields' => self::TASK_FIELDS]);
        if ($response->status() === 404) {
            return null;
        }
        $data = $this->guard($response, '/tasks/{gid}')['data'] ?? null;

        return is_array($data) && (string) data_get($data, 'workspace.gid') === $this->workspace() ? $data : null;
    }

    /** Leseprobe des gewählten Workspaces (Healthcheck). */
    public function probe(): void {
        $this->guard($this->api()->getResponse($this->url('/workspaces/' . $this->workspace()), ['opt_fields' => 'gid']), '/workspaces/{gid}');
    }

    /** @param array<string, string> $query */
    private function page(string $path, array $query, ?string $offset): CursorPage {
        $response = $this->api()->getResponse($this->url($path), $query + ['limit' => $this->pageSize] + ($offset !== null ? ['offset' => $offset] : []));
        if ($offset !== null && $response->status() === 400) {
            throw new AsanaApiException('Asana: Seitenposition abgelaufen.', 400, $path, 'offset_rejected');
        }
        $body = $this->guard($response, $path);
        if (! is_array($body['data'] ?? null)) {
            throw new IncompleteRemoteReadException('invalid_response');
        }
        $next = data_get($body, 'next_page.offset');

        return new CursorPage($body['data'], is_string($next) && $next !== '' ? $next : null);
    }

    /** Absolute Adresse: ein relativer Pfad mit führendem Schrägstrich verlöre den Basispfad `/api/1.0`. */
    private function url(string $path): string {
        return $this->apiBase . $path;
    }

    private function workspace(): string {
        $gid = (string) $this->connection->source_id;
        if (! AsanaPlugin::isGid($gid)) {
            throw new AsanaApiException('Asana: kein gültiger Workspace gewählt.', 0, 'workspace');
        }

        return $gid;
    }

    private function api(): PluginApiClient {
        if ($this->api === null) {
            $this->api = $this->http->client(AsanaPlugin::ID, $this->apiBase);
            $this->api->setAuthentication(new SerializedBearerAuthentication($this->connection, app(AsanaOAuthGrant::class)->grant()));
        }

        return $this->api;
    }

    /** @return class-string<\App\Plugins\Support\PluginApiException> */
    protected function apiExceptionClass(): string {
        return AsanaApiException::class;
    }

    protected function apiLabel(): string {
        return 'Asana';
    }
}
