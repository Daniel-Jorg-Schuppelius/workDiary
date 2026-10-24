<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KanboardClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Kanboard\Api;

use APIToolkit\API\Authentication\BasicAuthentication;
use App\Plugins\Kanboard\Exceptions\KanboardApiException;
use App\Plugins\Kanboard\KanboardPlugin;
use App\Plugins\Support\{CallsJsonRpc, GuardsPluginApiResponses, PluginApiClient, PluginHttpFactory};
use App\Plugins\Support\TaskSync\IncompleteRemoteReadException;

/**
 * Lesezugriff auf die JSON-RPC-API von Kanboard (`/jsonrpc.php`). Ohne
 * Benutzername gilt der Anwendungszugang (`jsonrpc`, alle Projekte), mit
 * Benutzername das persönliche Token und nur dessen Projekte — die
 * `getMy…`-Prozeduren lehnt Kanboard für den Anwendungszugang ab.
 */
class KanboardClient {
    use CallsJsonRpc;
    use GuardsPluginApiResponses;

    private const APPLICATION_USER = 'jsonrpc';

    private ?PluginApiClient $api = null;

    public function __construct(
        private readonly PluginHttpFactory $http,
        private readonly string $baseUrl,
        private readonly ?string $username,
        private readonly string $apiToken,
    ) {}

    /** @return array{id: ?string, name: ?string, version: ?string} */
    public function account(): array {
        $version = $this->call('getVersion');
        $version = is_string($version) ? $version : null;
        if ($this->isApplicationAccess()) {
            return ['id' => null, 'name' => self::APPLICATION_USER, 'version' => $version];
        }
        $me = $this->call('getMe');
        if (! is_array($me)) {
            throw new IncompleteRemoteReadException('invalid_response');
        }
        $name = is_string($me['name'] ?? null) && trim($me['name']) !== '' ? $me['name'] : ($me['username'] ?? null);

        return [
            'id' => is_scalar($me['id'] ?? null) ? (string) $me['id'] : null,
            'name' => is_string($name) ? $name : null,
            'version' => $version,
        ];
    }

    /** @return list<array{id: string, key: string, name: string}> */
    public function projects(): array {
        $projects = [];
        foreach ($this->list($this->isApplicationAccess() ? 'getAllProjects' : 'getMyProjects') as $project) {
            $id = is_array($project) && is_scalar($project['id'] ?? null) ? (string) $project['id'] : '';
            // getAllProjects liefert auch geschlossene Projekte, getMyProjects nicht — gleich behandeln.
            if (ctype_digit($id) && is_string($project['name'] ?? null) && (string) ($project['is_active'] ?? '1') === '1') {
                $key = is_string($project['identifier'] ?? null) && $project['identifier'] !== '' ? $project['identifier'] : $id;
                $projects[] = ['id' => $id, 'key' => $key, 'name' => $project['name']];
            }
        }

        return $projects;
    }

    /**
     * Aufgaben eines Projekts — Kanboard liefert je Status alles in einer Antwort.
     *
     * @return list<mixed>
     */
    public function tasks(string $projectId, bool $openOnly): array {
        if (! ctype_digit($projectId)) {
            throw new IncompleteRemoteReadException('invalid_project');
        }
        // Mit dem Anwendungszugang prüft getAllTasks das Projekt nicht: ein fehlendes ergäbe still eine leere Liste.
        if (! is_array($this->call('getProjectById', ['project_id' => (int) $projectId]))) {
            throw new IncompleteRemoteReadException('project_missing');
        }
        $tasks = $this->list('getAllTasks', ['project_id' => (int) $projectId, 'status_id' => 1]);

        return $openOnly ? $tasks : [...$tasks, ...$this->list('getAllTasks', ['project_id' => (int) $projectId, 'status_id' => 0])];
    }

    /**
     * Einzelne Aufgabe; null, wenn sie fehlt oder der Zugang sie nicht sehen darf.
     *
     * @return array<string, mixed>|null
     */
    public function task(string $taskId): ?array {
        if (! ctype_digit($taskId)) {
            return null;
        }
        $task = $this->call('getTask', ['task_id' => (int) $taskId], allowForbidden: true);

        /** @var array<string, mixed>|null */
        return is_array($task) ? $task : null;
    }

    /** Leseprobe der Instanz (Healthcheck). */
    public function probe(): void {
        $this->call('getVersion');
    }

    /**
     * @param  array<string, mixed>  $params
     * @return list<mixed>
     */
    private function list(string $method, array $params = []): array {
        $result = $this->call($method, $params);
        if (! is_array($result) || ! array_is_list($result)) {
            throw new IncompleteRemoteReadException('invalid_response');
        }

        return $result;
    }

    /** @param array<string, mixed> $params */
    private function call(string $method, array $params = [], bool $allowForbidden = false): mixed {
        return $this->jsonRpc($this->api(), $this->baseUrl . '/jsonrpc.php', $method, $params, $allowForbidden);
    }

    private function isApplicationAccess(): bool {
        return $this->username === null || $this->username === self::APPLICATION_USER;
    }

    private function api(): PluginApiClient {
        if ($this->api === null) {
            $this->api = $this->http->client(KanboardPlugin::ID, $this->baseUrl);
            $this->api->setAuthentication(new BasicAuthentication($this->username ?? self::APPLICATION_USER, $this->apiToken));
        }

        return $this->api;
    }

    /** @return class-string<\App\Plugins\Support\PluginApiException> */
    protected function apiExceptionClass(): string {
        return KanboardApiException::class;
    }

    protected function apiLabel(): string {
        return 'Kanboard';
    }
}
