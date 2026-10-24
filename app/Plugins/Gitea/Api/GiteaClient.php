<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GiteaClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Gitea\Api;

use APIToolkit\API\Authentication\ApiKeyAuthentication;
use APIToolkit\API\Pagination\CursorPage;
use App\Plugins\Gitea\Exceptions\GiteaApiException;
use App\Plugins\Gitea\GiteaPlugin;
use App\Plugins\Support\{GuardsPluginApiResponses, PluginApiClient, PluginHttpFactory};
use App\Plugins\Support\TaskSync\{IncompleteRemoteReadException, RemotePages};
use Generator;

/**
 * Lesezugriff auf die REST-API von Gitea und Forgejo (`/api/v1`): Konto,
 * Version, Repositories, Issues eines Repositories. Seiten über `page`/`limit`,
 * das Ende über `X-Total-Count`.
 */
class GiteaClient {
    use GuardsPluginApiResponses;

    private ?PluginApiClient $api = null;

    public function __construct(
        private readonly PluginHttpFactory $http,
        private readonly string $baseUrl,
        private readonly string $apiToken,
        private readonly int $pageSize,
        private readonly int $maxPages,
    ) {}

    /** @return array{id: ?string, login: ?string} */
    public function currentUser(): array {
        $user = $this->guard($this->api()->getResponse($this->url('/user')), '/user');

        return [
            'id' => is_scalar($user['id'] ?? null) ? (string) $user['id'] : null,
            'login' => is_string($user['login'] ?? null) ? $user['login'] : null,
        ];
    }

    public function version(): string {
        $body = $this->guard($this->api()->getResponse($this->url('/version')), '/version');

        return is_string($body['version'] ?? null) ? $body['version'] : '';
    }

    /**
     * Repositories, auf die der Token Zugriff hat.
     *
     * @return list<array{id: string, key: string, name: string}>
     */
    public function repositories(): array {
        $repositories = [];
        // `order_by=id` ist nur bei Forgejo dokumentiert; Gitea sortiert nach Namen und überliest den Parameter.
        foreach ($this->pages('/user/repos', ['order_by' => 'id']) as $repo) {
            if (is_array($repo) && is_scalar($repo['id'] ?? null) && ctype_digit((string) $repo['id']) && is_string($repo['full_name'] ?? null)) {
                $repositories[] = ['id' => (string) $repo['id'], 'key' => $repo['full_name'], 'name' => $repo['full_name']];
            }
        }

        return $repositories;
    }

    /**
     * Repository über die stabile ID (ein Umbenennen ändert nur den Namen); null bei 404.
     *
     * @return array<string, mixed>|null
     */
    public function repository(string $repositoryId): ?array {
        if (! ctype_digit($repositoryId)) {
            return null;
        }
        $response = $this->api()->getResponse($this->url('/repositories/' . $repositoryId));
        if ($response->status() === 404) {
            return null;
        }

        /** @var array<string, mixed> */
        return $this->guard($response, '/repositories/{id}');
    }

    /**
     * Issues (ohne Pull Requests) eines Repositories; `$since` als RFC 3339.
     *
     * @return Generator<int, mixed>
     */
    public function issues(string $fullName, string $state, ?string $since): Generator {
        if (preg_match('#^([^/\s]+)/([^/\s]+)$#', $fullName, $m) !== 1) {
            throw new IncompleteRemoteReadException('invalid_repository');
        }

        return $this->pages('/repos/' . rawurlencode($m[1]) . '/' . rawurlencode($m[2]) . '/issues', array_filter([
            'state' => $state,
            'type' => 'issues',
            'since' => $since,
            // Forgejo: älteste zuerst, damit Seiten nicht wandern; Gitea kennt den Parameter nicht und liefert neueste zuerst.
            'sort' => 'oldest',
        ], static fn (?string $v): bool => $v !== null));
    }

    /** Leseprobe der Instanz (Healthcheck). */
    public function probe(): void {
        $this->currentUser();
    }

    /**
     * @param  array<string, string>  $query
     * @return Generator<int, mixed>
     */
    private function pages(string $path, array $query): Generator {
        $seen = 0;

        return RemotePages::all(function (?string $page) use ($path, $query, &$seen): CursorPage {
            $current = (int) ($page ?? 1);
            $response = $this->api()->getResponse($this->url($path), $query + ['page' => $current, 'limit' => $this->pageSize]);
            $items = $this->guard($response, $path);
            if (! array_is_list($items)) {
                throw new IncompleteRemoteReadException('invalid_response');
            }
            // Die Instanz kann `limit` kappen (MAX_RESPONSE_ITEMS) — nur die Gesamtzahl zeigt das Ende.
            $seen += count($items);
            $total = $response->header('X-Total-Count');
            if (! is_numeric($total)) {
                throw new IncompleteRemoteReadException('invalid_response');
            }
            $more = $items !== [] && $seen < (int) $total;

            return new CursorPage($items, $more ? (string) ($current + 1) : null);
        }, $this->maxPages);
    }

    private function url(string $path): string {
        return $this->baseUrl . '/api/v1' . $path;
    }

    private function api(): PluginApiClient {
        if ($this->api === null) {
            $this->api = $this->http->client(GiteaPlugin::ID, $this->baseUrl);
            // `token <wert>` ist bei Gitea und Forgejo dokumentiert, `Bearer` nur bei Forgejo.
            $this->api->setAuthentication(new ApiKeyAuthentication('token ' . $this->apiToken, 'Authorization'));
        }

        return $this->api;
    }

    /** @return class-string<\App\Plugins\Support\PluginApiException> */
    protected function apiExceptionClass(): string {
        return GiteaApiException::class;
    }

    protected function apiLabel(): string {
        return 'Gitea';
    }
}
