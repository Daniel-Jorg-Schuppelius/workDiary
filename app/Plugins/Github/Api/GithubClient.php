<?php
/*
 * Created on   : Sat Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GithubClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Github\Api;

use App\Plugins\Github\GithubPlugin;
use App\Plugins\Support\PluginHttpFactory;
use Illuminate\Http\Client\Response;

/**
 * Typisierte Oberfläche der GitHub-REST-API v3 (Feature 060, MVP-129,
 * api.github.com). HTTP über {@see \App\Plugins\Support\PluginApiClient}
 * (php-api-toolkit: Retry/Backoff inkl. Retry-After — GitHub-Rate-Limit
 * 5.000/h je PAT).
 *
 * Auth: `Authorization: Bearer <PAT>` (Fine-grained PAT, Lesezugriff auf
 * Issues genügt); Pflicht-Header `Accept: application/vnd.github+json` und
 * `X-GitHub-Api-Version`. Die Issues-Liste enthält AUCH Pull Requests
 * (`pull_request`-Schlüssel) — das Filtern übernimmt der Importer.
 */
class GithubClient {
    public function __construct(
        private readonly PluginHttpFactory $http,
        private readonly string $apiToken,
        private readonly string $baseUrl,
    ) {}

    /**
     * GET /repos/{owner}/{repo}/issues — nach `updated_at` aufsteigend, damit
     * der `since`-Aufholpunkt monoton fortgeschrieben werden kann.
     * `per_page` ist API-seitig auf 100 begrenzt.
     *
     * @return array<int, array<string, mixed>>
     */
    public function issues(string $owner, string $repo, ?string $since, int $page = 1, int $perPage = 100): array {
        $query = [
            'state' => 'all',
            'sort' => 'updated',
            'direction' => 'asc',
            'per_page' => min(max(1, $perPage), 100),
            'page' => max(1, $page),
        ];
        if ($since !== null && $since !== '') {
            $query['since'] = $since;
        }

        $endpoint = sprintf('/repos/%s/%s/issues', rawurlencode($owner), rawurlencode($repo));
        $body = $this->guard($this->authed('get', $endpoint, ['query' => $query]), $endpoint);

        return array_values(array_filter($body, 'is_array'));
    }

    /**
     * GET /user — billige Healthcheck-Probe (Token gültig + API erreichbar).
     *
     * @return array<string, mixed>
     */
    public function user(): array {
        /** @var array<string, mixed> */
        return $this->guard($this->authed('get', '/user'), '/user');
    }

    /** @param array<string, mixed> $options */
    private function authed(string $method, string $path, array $options = []): Response {
        $options['headers'] = array_merge(
            (array) ($options['headers'] ?? []),
            [
                'Authorization' => 'Bearer ' . $this->apiToken,
                'Accept' => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => (string) config('plugins.github.api_version', '2022-11-28'),
            ],
        );

        return $this->http->client(GithubPlugin::ID, $this->baseUrl)
            ->requestResponse($method, $this->baseUrl . $path, $options);
    }

    /** @return array<mixed> */
    private function guard(Response $response, string $endpoint): array {
        if (! $response->successful()) {
            throw new GithubApiException(
                $response->status(),
                sprintf('GitHub %s: HTTP %d %s', $endpoint, $response->status(), mb_substr((string) $response->body(), 0, 300)),
                $endpoint,
            );
        }

        return (array) ($response->json() ?? []);
    }
}
