<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PlaneClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Plane\Api;

use APIToolkit\API\Authentication\ApiKeyAuthentication;
use APIToolkit\API\Pagination\CursorPage;
use App\Plugins\Plane\Exceptions\{PlaneApiException, PlaneVersionUnsupportedException};
use App\Plugins\Plane\PlanePlugin;
use App\Plugins\Support\{GuardsPluginApiResponses, PluginApiClient, PluginHttpFactory};
use App\Plugins\Support\TaskSync\{IncompleteRemoteReadException, RemotePages};
use Generator;

/**
 * Lesezugriff auf die REST-API von Plane (`/api/v1/workspaces/<slug>`):
 * Profil, Projekte, Zustände und Arbeitspakete eines Projekts. Seiten über
 * `cursor`/`per_page` (höchstens 100) bis `next_page_results` falsch ist.
 * Arbeitspakete liegen seit 2025 unter `work-items/`; ältere Instanzen kennen
 * nur `issues/` — ein 404 der neuen Liste schaltet einmal je Client um.
 */
class PlaneClient {
    use GuardsPluginApiResponses;

    /** Plane Cloud trennt API- und App-Host; Links zeigen auf die App. */
    private const CLOUD_API_HOST = 'api.plane.so';

    private const CLOUD_APP_URL = 'https://app.plane.so';

    private ?PluginApiClient $api = null;

    private string $itemsPath = 'work-items';

    public function __construct(
        private readonly PluginHttpFactory $http,
        private readonly string $baseUrl,
        private readonly string $workspaceSlug,
        private readonly string $apiKey,
        private readonly int $pageSize,
        private readonly int $maxPages,
    ) {}

    /** Adresse des Workspaces in der Plane-Oberfläche — Instanz und Linkbasis der Verbindung. */
    public function workspaceUrl(): string {
        $host = strtolower((string) parse_url($this->baseUrl, PHP_URL_HOST));

        return ($host === self::CLOUD_API_HOST ? self::CLOUD_APP_URL : $this->baseUrl) . '/' . $this->slug();
    }

    /** @return array{id: ?string, name: ?string, version: ?string} */
    public function account(): array {
        $response = $this->api()->getResponse($this->baseUrl . '/api/v1/users/me/');
        if ($response->status() === 404) {
            throw new PlaneVersionUnsupportedException('Plane ab Version 0.28 erforderlich.');
        }
        $me = $this->guard($response, '/users/me');
        // Der Workspace muss lesbar sein — sonst scheitert erst der erste Lauf.
        $this->guard($this->api()->getResponse($this->workspace('/projects/'), ['per_page' => 1]), '/projects');
        $name = is_string($me['display_name'] ?? null) && $me['display_name'] !== '' ? $me['display_name'] : ($me['email'] ?? null);

        return [
            'id' => is_string($me['id'] ?? null) ? $me['id'] : null,
            'name' => is_string($name) ? $name : null,
            'version' => null,
        ];
    }

    /** @return list<array{id: string, key: string, name: string}> */
    public function projects(): array {
        $projects = [];
        foreach ($this->pages($this->workspace('/projects/'), []) as $project) {
            // Die Liste enthält auch öffentliche Projekte ohne Mitgliedschaft; deren Arbeitspakete liefern 403.
            if (is_array($project) && is_string($project['id'] ?? null) && is_string($project['name'] ?? null)
                && ($project['archived_at'] ?? null) === null && ($project['is_member'] ?? false) === true) {
                $key = is_string($project['identifier'] ?? null) && $project['identifier'] !== '' ? $project['identifier'] : $project['name'];
                $projects[] = ['id' => $project['id'], 'key' => $key, 'name' => $project['name']];
            }
        }

        return $projects;
    }

    /**
     * Zustandsgruppe je Zustands-ID eines Projekts.
     *
     * @return array<string, string>
     */
    public function stateGroups(string $projectId): array {
        $groups = [];
        foreach ($this->pages($this->project($projectId, '/states/'), []) as $state) {
            if (is_array($state) && is_string($state['id'] ?? null) && is_string($state['group'] ?? null)) {
                $groups[$state['id']] = $state['group'];
            }
        }

        return $groups;
    }

    /**
     * Arbeitspakete eines Projekts nach fortlaufender Nummer (eindeutig, neue
     * kommen ans Ende); archivierte, Entwürfe und Triage liefert die API nicht.
     *
     * @return Generator<int, mixed>
     */
    public function workItems(string $projectId): Generator {
        $first = $this->api()->getResponse($this->project($projectId, '/' . $this->itemsPath . '/'), ['per_page' => 1]);
        if ($first->status() === 404 && $this->itemsPath === 'work-items') {
            $this->itemsPath = 'issues';
        }

        return $this->pages($this->project($projectId, '/' . $this->itemsPath . '/'), ['order_by' => 'sequence_id']);
    }

    /**
     * Einzelnes Arbeitspaket; null bei 404 oder ohne Mitgliedschaft (403).
     *
     * @return array<string, mixed>|null
     */
    public function workItem(string $projectId, string $itemId): ?array {
        if (preg_match('/^[0-9a-f-]{36}$/i', $itemId) !== 1) {
            return null;
        }
        foreach (array_unique([$this->itemsPath, 'issues']) as $path) {
            $response = $this->api()->getResponse($this->project($projectId, '/' . $path . '/' . $itemId . '/'));
            if ($response->status() === 403) {
                return null;
            }
            if ($response->status() !== 404) {
                /** @var array<string, mixed> */
                return $this->guard($response, '/' . $path . '/{id}');
            }
        }

        return null;
    }

    /** Leseprobe der Instanz (Healthcheck). */
    public function probe(): void {
        $this->guard($this->api()->getResponse($this->baseUrl . '/api/v1/users/me/'), '/users/me');
    }

    /**
     * Liest Antworten mit `results` seitenweise; eine reine Liste gilt als einzige Seite.
     *
     * @param  array<string, string>  $query
     * @return Generator<int, mixed>
     */
    private function pages(string $url, array $query): Generator {
        return RemotePages::all(function (?string $cursor) use ($url, $query): CursorPage {
            $response = $this->api()->getResponse($url, $query + ['per_page' => min(100, $this->pageSize)] + ($cursor !== null ? ['cursor' => $cursor] : []));
            $body = $this->guard($response, (string) parse_url($url, PHP_URL_PATH));
            if (array_is_list($body)) {
                return new CursorPage($body, null);
            }
            if (! is_array($body['results'] ?? null) || ! array_is_list($body['results'])) {
                throw new IncompleteRemoteReadException('invalid_response');
            }
            $next = ($body['next_page_results'] ?? false) === true && is_string($body['next_cursor'] ?? null) ? $body['next_cursor'] : null;

            return new CursorPage($body['results'], $next);
        }, $this->maxPages);
    }

    private function workspace(string $path): string {
        return $this->baseUrl . '/api/v1/workspaces/' . $this->slug() . $path;
    }

    private function project(string $projectId, string $path): string {
        if (preg_match('/^[0-9a-f-]{36}$/i', $projectId) !== 1) {
            throw new IncompleteRemoteReadException('invalid_project');
        }

        return $this->workspace('/projects/' . strtolower($projectId) . $path);
    }

    private function slug(): string {
        $slug = trim($this->workspaceSlug, " /\t");
        if (preg_match('/^[A-Za-z0-9_-]+$/', $slug) !== 1) {
            throw new IncompleteRemoteReadException('invalid_workspace');
        }

        return $slug;
    }

    private function api(): PluginApiClient {
        if ($this->api === null) {
            // Plane erlaubt 60 Anfragen je Minute und Schlüssel.
            $this->api = $this->http->client(PlanePlugin::ID, $this->baseUrl, 1.0);
            $this->api->setAuthentication(new ApiKeyAuthentication($this->apiKey, 'X-API-Key'));
        }

        return $this->api;
    }

    /** @return class-string<\App\Plugins\Support\PluginApiException> */
    protected function apiExceptionClass(): string {
        return PlaneApiException::class;
    }

    protected function apiLabel(): string {
        return 'Plane';
    }
}
