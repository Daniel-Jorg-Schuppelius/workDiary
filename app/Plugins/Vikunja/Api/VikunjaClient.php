<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VikunjaClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Vikunja\Api;

use APIToolkit\API\Authentication\BearerAuthentication;
use APIToolkit\API\Pagination\CursorPage;
use App\Plugins\Support\{GuardsPluginApiResponses, PluginApiClient, PluginHttpFactory};
use App\Plugins\Support\TaskSync\{IncompleteRemoteReadException, RemotePages};
use App\Plugins\Vikunja\Exceptions\{VikunjaApiException, VikunjaVersionUnsupportedException};
use App\Plugins\Vikunja\VikunjaPlugin;
use Generator;

/**
 * Lesezugriff auf die REST-API von Vikunja ab 1.0 (`/api/v1`): Version,
 * Projekte, Aufgaben über `GET /tasks` mit Filter. Seiten über
 * `page`/`per_page`, das Ende über `x-pagination-total-pages` (die Instanz
 * kappt `per_page` auf ihr Maximum). Token-Rechte: Projekte und Aufgaben
 * lesen, optional „other → user“ für den Kontonamen.
 */
class VikunjaClient {
    use GuardsPluginApiResponses;

    private ?PluginApiClient $api = null;

    public function __construct(
        private readonly PluginHttpFactory $http,
        private readonly string $baseUrl,
        private readonly string $apiToken,
        private readonly int $pageSize,
        private readonly int $maxPages,
    ) {}

    /**
     * Version, Zugriff und — wenn das Token „other → user“ darf — das Konto.
     *
     * @return array{id: ?string, name: ?string, version: ?string}
     */
    public function account(): array {
        $info = $this->guard($this->api()->getResponse($this->url('/info')), '/info');
        $version = is_string($info['version'] ?? null) ? $info['version'] : null;
        if ($version !== null && preg_match('/^v?(\d+)\./', $version, $m) === 1 && (int) $m[1] < 1) {
            throw new VikunjaVersionUnsupportedException('Vikunja ab Version 1.0 erforderlich.');
        }
        $this->probe();
        $response = $this->api()->getResponse($this->url('/user'));
        $user = in_array($response->status(), [401, 403], true) ? [] : $this->guard($response, '/user');
        $name = is_string($user['name'] ?? null) && trim($user['name']) !== '' ? $user['name'] : ($user['username'] ?? null);

        return [
            'id' => is_scalar($user['id'] ?? null) ? (string) $user['id'] : null,
            'name' => is_string($name) ? $name : null,
            'version' => $version,
        ];
    }

    /**
     * Projekte, auf die der Token Zugriff hat; das Pseudoprojekt „Favoriten“
     * (negative ID) bleibt außen vor.
     *
     * @return list<array{id: string, key: string, name: string}>
     */
    public function projects(): array {
        $projects = [];
        foreach ($this->pages('/projects', []) as $project) {
            if (is_array($project) && is_int($project['id'] ?? null) && $project['id'] > 0 && is_string($project['title'] ?? null)) {
                $key = is_string($project['identifier'] ?? null) && $project['identifier'] !== '' ? $project['identifier'] : (string) $project['id'];
                $projects[] = ['id' => (string) $project['id'], 'key' => $key, 'name' => $project['title']];
            }
        }

        return $projects;
    }

    /**
     * Aufgaben eines Projekts über die dokumentierte Sammlung `GET /tasks`,
     * nach ID sortiert; `$openOnly` filtert serverseitig auf nicht erledigte.
     * Aufgaben archivierter Projekte liefert Vikunja hier nicht.
     *
     * @return Generator<int, mixed>
     */
    public function tasks(string $projectId, bool $openOnly): Generator {
        if (! ctype_digit($projectId)) {
            throw new IncompleteRemoteReadException('invalid_project');
        }

        return $this->pages('/tasks', ['filter' => 'project = ' . $projectId . ($openOnly ? ' && done = false' : ''), 'sort_by' => 'id', 'order_by' => 'asc']);
    }

    /**
     * Einzelne Aufgabe; null bei 404 oder fehlendem Recht.
     *
     * @return array<string, mixed>|null
     */
    public function task(string $taskId): ?array {
        if (! ctype_digit($taskId)) {
            return null;
        }
        $response = $this->api()->getResponse($this->url('/tasks/' . $taskId));
        if (in_array($response->status(), [403, 404], true)) {
            return null;
        }

        /** @var array<string, mixed> */
        return $this->guard($response, '/tasks/{id}');
    }

    /** Leseprobe mit dem Recht, das der Import ohnehin braucht (Projekte lesen). */
    public function probe(): void {
        $this->guard($this->api()->getResponse($this->url('/projects'), ['per_page' => 1]), '/projects');
    }

    /**
     * @param  array<string, string>  $query
     * @return Generator<int, mixed>
     */
    private function pages(string $path, array $query): Generator {
        return RemotePages::all(function (?string $page) use ($path, $query): CursorPage {
            $current = (int) ($page ?? 1);
            $response = $this->api()->getResponse($this->url($path), $query + ['page' => $current, 'per_page' => $this->pageSize]);
            $this->assertOk($response, $path);
            $items = $response->json();
            $total = $response->header('x-pagination-total-pages');
            // Ohne die dokumentierte Seitenzahl lässt sich ein gekapptes per_page nicht erkennen.
            if (! is_array($items) || ! array_is_list($items) || ! is_numeric($total)) {
                throw new IncompleteRemoteReadException('invalid_response');
            }
            $more = $current < (int) $total;

            return new CursorPage($items, $more ? (string) ($current + 1) : null);
        }, $this->maxPages);
    }

    private function url(string $path): string {
        return $this->baseUrl . '/api/v1' . $path;
    }

    private function api(): PluginApiClient {
        if ($this->api === null) {
            $this->api = $this->http->client(VikunjaPlugin::ID, $this->baseUrl);
            $this->api->setAuthentication(new BearerAuthentication($this->apiToken));
        }

        return $this->api;
    }

    /** @return class-string<\App\Plugins\Support\PluginApiException> */
    protected function apiExceptionClass(): string {
        return VikunjaApiException::class;
    }

    protected function apiLabel(): string {
        return 'Vikunja';
    }
}
