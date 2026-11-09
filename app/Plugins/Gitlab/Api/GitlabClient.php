<?php
/*
 * Created on   : Sat Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GitlabClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Gitlab\Api;

use App\Plugins\Gitlab\Exceptions\GitlabApiException;
use App\Plugins\Gitlab\GitlabPlugin;
use App\Plugins\Support\PluginHttpFactory;
use Illuminate\Http\Client\Response;

/**
 * Typisierte Oberfläche der GitLab-REST-API v4 (Feature 060, MVP-129;
 * gitlab.com oder self-hosted). HTTP über
 * {@see \App\Plugins\Support\PluginApiClient} (php-api-toolkit: Retry/Backoff
 * inkl. Retry-After).
 *
 * Auth: `PRIVATE-TOKEN`-Header (Project Access Token empfohlen). Issues je
 * Projekt über `GET /api/v4/projects/{id}/issues` (`updated_after`,
 * per_page ≤ 100). Identität eines Issues ist `iid` + Projekt — die globale
 * `id` wird bewusst nicht als Schlüssel verwendet (Recherche 2026-07).
 */
class GitlabClient {
    // Gemeinsame guard()-Fehlerbehandlung (Vollaudit 2026-07, N33).
    use \App\Plugins\Support\GuardsPluginApiResponses;

    public function __construct(
        private readonly PluginHttpFactory $http,
        private readonly string $apiToken,
        private readonly string $baseUrl,
    ) {}

    /**
     * GET /api/v4/projects/{id}/issues — nach `updated_at` aufsteigend, damit
     * der `updated_after`-Aufholpunkt monoton fortgeschrieben werden kann.
     *
     * @return list<array<string, mixed>>
     */
    public function issues(string $projectId, ?string $updatedAfter, int $page = 1, int $perPage = 100): array {
        $query = [
            'state' => 'all',
            'order_by' => 'updated_at',
            'sort' => 'asc',
            'per_page' => min(max(1, $perPage), 100),
            'page' => max(1, $page),
        ];
        if ($updatedAfter !== null && $updatedAfter !== '') {
            $query['updated_after'] = $updatedAfter;
        }

        $endpoint = sprintf('/api/v4/projects/%s/issues', rawurlencode($projectId));
        $body = $this->guard($this->authed('get', $endpoint, ['query' => $query]), $endpoint);

        return array_values(array_filter($body, 'is_array'));
    }

    /**
     * GET /api/v4/user — billige Healthcheck-Probe (Token gültig + API erreichbar).
     *
     * @return array<string, mixed>
     */
    public function user(): array {
        /** @var array<string, mixed> */
        return $this->guard($this->authed('get', '/api/v4/user'), '/api/v4/user');
    }

    /** @param array<string, mixed> $options */
    private function authed(string $method, string $path, array $options = []): Response {
        $options['headers'] = array_merge(
            (array) ($options['headers'] ?? []),
            ['PRIVATE-TOKEN' => $this->apiToken],
        );

        return $this->http->client(GitlabPlugin::ID, $this->baseUrl)
            ->requestResponse($method, $this->baseUrl . $path, $options);
    }

    /**
     * PUT /api/v4/projects/{id}/issues/{iid} — Issue schließen/öffnen
     * (Rückrichtung, Audit 2026-08 Welle 1.4) über `state_event`. GitLab
     * beantwortet ein No-op-Event mit dem unveränderten Issue (idempotent).
     */
    public function setIssueState(string $projectId, int $iid, bool $closed): void {
        $endpoint = sprintf('/api/v4/projects/%s/issues/%d', rawurlencode($projectId), $iid);
        $this->guard($this->authed('put', $endpoint, ['json' => ['state_event' => $closed ? 'close' : 'reopen']]), $endpoint);
    }

    /** POST /api/v4/projects/{id}/issues/{iid}/notes — Erledigungs-Notiz. */
    public function commentIssue(string $projectId, int $iid, string $body): void {
        $endpoint = sprintf('/api/v4/projects/%s/issues/%d/notes', rawurlencode($projectId), $iid);
        $this->guard($this->authed('post', $endpoint, ['json' => ['body' => $body]]), $endpoint);
    }

    /**
     * POST /api/v4/projects/{id}/issues/{iid}/add_spent_time — bucht Zeit auf
     * das Issue (MVP-1513). `duration` im Format der Doku („3h30m“, ohne Tage:
     * GitLab rechnet 1d = 8h). Die Antwort trägt nur die Summen des Issues,
     * keine ID der Buchung.
     *
     * @return array<string, mixed>
     */
    public function addSpentTime(string $projectId, int $iid, int $minutes, ?string $summary = null): array {
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;
        $duration = ($hours > 0 ? $hours . 'h' : '') . ($rest > 0 || $hours === 0 ? $rest . 'm' : '');

        $endpoint = sprintf('/api/v4/projects/%s/issues/%d/add_spent_time', rawurlencode($projectId), $iid);

        /** @var array<string, mixed> */
        return $this->guard($this->authed('post', $endpoint, ['json' => array_filter([
            'duration' => $duration,
            'summary' => $summary,
        ], static fn (?string $value): bool => $value !== null && $value !== '')]), $endpoint);
    }

    /** @return class-string<\App\Plugins\Support\PluginApiException> */
    protected function apiExceptionClass(): string {
        return GitlabApiException::class;
    }

    protected function apiLabel(): string {
        return 'GitLab';
    }
}
