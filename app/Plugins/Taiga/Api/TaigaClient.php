<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TaigaClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Taiga\Api;

use APIToolkit\API\Authentication\BearerAuthentication;
use APIToolkit\API\Pagination\CursorPage;
use App\Plugins\Support\{GuardsPluginApiResponses, PluginApiClient, PluginHttpFactory};
use App\Plugins\Support\TaskSync\{IncompleteRemoteReadException, RemotePages};
use App\Plugins\Taiga\Exceptions\TaigaApiException;
use App\Plugins\Taiga\TaigaPlugin;
use Generator;

/**
 * Lesezugriff auf die REST-API von Taiga (`/api/v1`). Taiga kennt keine
 * dauerhaften API-Token: der Client meldet sich beim ersten Aufruf mit
 * Benutzername und Passwort an und nutzt das Sitzungstoken für diesen Lauf.
 * Seiten über `page`, das Ende über `x-pagination-next`.
 */
class TaigaClient {
    use GuardsPluginApiResponses;

    /** Taiga Cloud trennt API- und App-Host; Links zeigen auf die App. */
    private const CLOUD_API_HOST = 'api.taiga.io';

    private const CLOUD_APP_URL = 'https://tree.taiga.io';

    private ?PluginApiClient $api = null;

    /** @var array<string, mixed>|null Antwort der Anmeldung (Benutzer) */
    private ?array $session = null;

    public function __construct(
        private readonly PluginHttpFactory $http,
        private readonly string $baseUrl,
        private readonly string $username,
        private readonly string $password,
        private readonly int $pageSize,
        private readonly int $maxPages,
    ) {}

    /** Adresse der Taiga-Oberfläche — Linkbasis der Verbindung. */
    public function appUrl(): string {
        return strtolower((string) parse_url($this->baseUrl, PHP_URL_HOST)) === self::CLOUD_API_HOST ? self::CLOUD_APP_URL : $this->baseUrl;
    }

    /** @return array{id: ?string, name: ?string, version: ?string} */
    public function account(): array {
        $user = $this->session();
        $name = is_string($user['full_name'] ?? null) && trim($user['full_name']) !== '' ? $user['full_name'] : ($user['username'] ?? null);

        return [
            'id' => is_scalar($user['id'] ?? null) ? (string) $user['id'] : null,
            'name' => is_string($name) ? $name : null,
            'version' => null,
        ];
    }

    /**
     * Projekte, in denen das Konto Mitglied ist.
     *
     * @return list<array{id: string, key: string, name: string}>
     */
    public function projects(): array {
        $userId = $this->account()['id'];
        $projects = [];
        foreach ($this->pages('/projects', $userId !== null ? ['member' => $userId] : []) as $project) {
            $id = is_array($project) && is_scalar($project['id'] ?? null) ? (string) $project['id'] : '';
            if (ctype_digit($id) && is_string($project['name'] ?? null)) {
                $key = is_string($project['slug'] ?? null) && $project['slug'] !== '' ? $project['slug'] : $id;
                $projects[] = ['id' => $id, 'key' => $key, 'name' => $project['name']];
            }
        }

        return $projects;
    }

    /**
     * Rechte des Kontos im Projekt (`my_permissions` des Projektdetails).
     *
     * @return list<string>
     */
    public function projectPermissions(string $projectId): array {
        if (! ctype_digit($projectId)) {
            throw new IncompleteRemoteReadException('invalid_project');
        }
        $project = $this->guard($this->api()->getResponse($this->url('/projects/' . $projectId)), '/projects/{id}');
        if (! is_array($project['my_permissions'] ?? null)) {
            throw new IncompleteRemoteReadException('invalid_response');
        }

        return array_values(array_filter($project['my_permissions'], 'is_string'));
    }

    /**
     * Userstories eines Projekts (Listenform ohne Beschreibung), nach Anlage
     * sortiert; `$filters` nur `is_closed`, `modified_date__gte` oder
     * `finish_date__gte` (ISO 8601).
     *
     * @param  array<string, string>  $filters
     * @return Generator<int, mixed>
     */
    public function userStories(string $projectId, array $filters): Generator {
        if (! ctype_digit($projectId)) {
            throw new IncompleteRemoteReadException('invalid_project');
        }

        return $this->pages('/userstories', ['project' => $projectId, 'order_by' => 'created_date'] + array_intersect_key($filters, array_flip(['is_closed', 'modified_date__gte', 'finish_date__gte'])));
    }

    /**
     * Einzelne Userstory mit Beschreibung; null bei 404 oder fehlendem Recht.
     *
     * @return array<string, mixed>|null
     */
    public function userStory(string $storyId): ?array {
        if (! ctype_digit($storyId)) {
            return null;
        }
        $response = $this->api()->getResponse($this->url('/userstories/' . $storyId));
        if (in_array($response->status(), [403, 404], true)) {
            return null;
        }

        /** @var array<string, mixed> */
        return $this->guard($response, '/userstories/{id}');
    }

    /** Leseprobe der Instanz (Healthcheck): die Anmeldung selbst. */
    public function probe(): void {
        $this->session();
    }

    /**
     * @param  array<string, string>  $query
     * @return Generator<int, mixed>
     */
    private function pages(string $path, array $query): Generator {
        return RemotePages::all(function (?string $page) use ($path, $query): CursorPage {
            $current = (int) ($page ?? 1);
            $response = $this->api()->getResponse($this->url($path), $query + ['page' => $current, 'page_size' => $this->pageSize]);
            $this->assertOk($response, $path);
            // Eine 200 ohne JSON-Liste (etwa eine Proxy-Seite) ist kein leeres Ergebnis.
            $items = $response->json();
            if (! is_array($items) || ! array_is_list($items)) {
                throw new IncompleteRemoteReadException('invalid_response');
            }
            $more = trim((string) $response->header('x-pagination-next')) !== '';

            return new CursorPage($items, $more && $items !== [] ? (string) ($current + 1) : null);
        }, $this->maxPages);
    }

    /** @return array<string, mixed> */
    private function session(): array {
        $this->api();

        return $this->session ?? [];
    }

    private function url(string $path): string {
        return $this->baseUrl . '/api/v1' . $path;
    }

    private function api(): PluginApiClient {
        if ($this->api === null) {
            $api = $this->http->client(TaigaPlugin::ID, $this->baseUrl);
            // Die Anmeldung stellt nur ein Token aus — wiederholbar.
            $response = $api->postJson($this->url('/auth'), ['type' => 'normal', 'username' => $this->username, 'password' => $this->password], ['retry_non_idempotent' => true]);
            // Falsche Zugangsdaten: 401, unvollständige Anfrage: 400 — beides als Anmeldefehler, damit die Verbindung ihn zeigt.
            if (in_array($response->status(), [400, 401], true)) {
                throw new TaigaApiException('Taiga /auth: HTTP ' . $response->status(), 401, '/auth');
            }
            $session = $this->guard($response, '/auth');
            if (! is_string($session['auth_token'] ?? null) || $session['auth_token'] === '') {
                throw new IncompleteRemoteReadException('invalid_response');
            }
            $api->setAuthentication(new BearerAuthentication($session['auth_token']));
            $this->session = $session;
            $this->api = $api;
        }

        return $this->api;
    }

    /** @return class-string<\App\Plugins\Support\PluginApiException> */
    protected function apiExceptionClass(): string {
        return TaigaApiException::class;
    }

    protected function apiLabel(): string {
        return 'Taiga';
    }
}
