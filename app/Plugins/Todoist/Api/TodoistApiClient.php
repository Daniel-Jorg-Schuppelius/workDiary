<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TodoistApiClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Todoist\Api;

use APIToolkit\API\Authentication\OAuth2\OAuth2BearerAuthentication;
use App\Models\TodoistConnection;
use App\Plugins\Support\{ConnectionTokenStore, PluginApiClient, PluginHttpFactory};
use App\Plugins\Todoist\TodoistConfig;
use RuntimeException;

/**
 * Todoist-API-Client (einheitliche API v1, Feature 055) auf dem
 * `php-api-toolkit`-Fundament: OAuth2-Bearer über den org-gebundenen
 * {@see ConnectionTokenStore}, cursor-basierte Pagination, Retry/Backoff inkl.
 * `Retry-After` (429) aus dem Toolkit. Der Transport kommt aus der
 * {@see PluginHttpFactory} — Tests ersetzen sie durch
 * {@see \Tests\Support\FakePluginHttp} (Guzzle-MockHandler-Muster).
 */
class TodoistApiClient {
    private PluginApiClient $api;

    private string $base;

    public function __construct(TodoistConnection $connection) {
        $this->base = TodoistConfig::resolve()['api_base'];
        $this->api = app(PluginHttpFactory::class)->client('todoist', $this->base);
        $this->api->setAuthentication(new OAuth2BearerAuthentication(new ConnectionTokenStore($connection)));
    }

    /**
     * Verbindungs-/Health-Check: eingeloggter Todoist-Benutzer.
     *
     * @return array<string, mixed>
     */
    public function getUser(): array {
        return $this->getJson($this->base . '/user');
    }

    /**
     * Alle Projekte (cursor-basiert vollständig ausgelesen).
     *
     * @return list<array<string, mixed>>
     */
    public function getProjects(): array {
        return $this->getAllPages($this->base . '/projects');
    }

    /** @return list<array<string, mixed>> */
    public function getSections(string $projectId): array {
        return $this->getAllPages($this->base . '/sections', ['project_id' => $projectId]);
    }

    /** @return list<array<string, mixed>> */
    public function getCollaborators(string $projectId): array {
        return $this->getAllPages($this->base . '/projects/' . $projectId . '/collaborators');
    }

    /**
     * Aktive Aufgaben eines Projekts (cursor-basiert vollständig).
     *
     * @return list<array<string, mixed>>
     */
    public function getTasks(string $projectId): array {
        return $this->getAllPages($this->base . '/tasks', ['project_id' => $projectId]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  string|null  $requestId  Stabiler Idempotenzschlüssel (Todoist
     *                                  `X-Request-Id`): verhindert eine
     *                                  Duplikat-Aufgabe, wenn der Queue-Retry
     *                                  nach Teil-Erfolg denselben Create
     *                                  erneut sendet.
     * @return array<string, mixed>
     */
    public function createTask(array $payload, ?string $requestId = null): array {
        $options = $requestId !== null && $requestId !== ''
            ? ['headers' => ['X-Request-Id' => $requestId]]
            : [];
        $response = $this->api->postJson($this->base . '/tasks', $payload, $options);
        $this->assertOk($response->status(), '/tasks');

        /** @var array<string, mixed> */
        return (array) $response->json();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateTask(string $taskId, array $payload): array {
        $response = $this->api->postJson($this->base . '/tasks/' . $taskId, $payload);
        $this->assertOk($response->status(), '/tasks/' . $taskId);

        /** @var array<string, mixed> */
        return (array) $response->json();
    }

    /**
     * Inkrementelles Delta über den Sync-Endpunkt: liefert nur seit dem
     * `sync_token` geänderte Ressourcen (`*` = Vollstand). Antwort enthält
     * das nächste `sync_token` sowie `full_sync`.
     *
     * @param  list<string>  $resourceTypes  z. B. ['items']
     * @return array<string, mixed>
     */
    public function sync(?string $syncToken, array $resourceTypes): array {
        $response = $this->api->postJson($this->base . '/sync', [
            'sync_token' => ($syncToken === null || $syncToken === '') ? '*' : $syncToken,
            'resource_types' => $resourceTypes,
        ]);
        $this->assertOk($response->status(), '/sync');

        /** @var array<string, mixed> */
        return (array) $response->json();
    }

    /** Frisches sync_token als Delta-Startpunkt (kleinste Ressource abfragen). */
    public function getLatestSyncToken(): ?string {
        $token = $this->sync(null, ['user'])['sync_token'] ?? null;

        return is_string($token) && $token !== '' ? $token : null;
    }

    public function closeTask(string $taskId): void {
        $response = $this->api->postJson($this->base . '/tasks/' . $taskId . '/close');
        $this->assertOk($response->status(), '/tasks/close');
    }

    public function reopenTask(string $taskId): void {
        $response = $this->api->postJson($this->base . '/tasks/' . $taskId . '/reopen');
        $this->assertOk($response->status(), '/tasks/reopen');
    }

    /** Verschiebt die Aufgabe in einen Abschnitt der Zielprojekt-Sektion. */
    public function moveTask(string $taskId, string $sectionId): void {
        $response = $this->api->postJson($this->base . '/tasks/' . $taskId . '/move', ['section_id' => $sectionId]);
        $this->assertOk($response->status(), '/tasks/move');
    }

    /**
     * GET mit Fehlerprüfung; liefert das dekodierte JSON-Objekt.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function getJson(string $path, array $query = []): array {
        $response = $this->api->getResponse($path, $query);
        $this->assertOk($response->status(), $path);

        /** @var array<string, mixed> */
        return (array) $response->json();
    }

    /**
     * Liest einen cursor-paginierten Endpunkt vollständig aus
     * (`results` + `next_cursor` gemäß API v1).
     *
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    private function getAllPages(string $path, array $query = []): array {
        $items = [];
        $cursor = null;

        do {
            $page = $this->getJson($path, $cursor !== null ? $query + ['cursor' => $cursor] : $query);
            /** @var list<array<string, mixed>> $results */
            $results = is_array($page['results'] ?? null) ? array_values($page['results']) : [];
            $items = array_merge($items, $results);
            $cursor = is_string($page['next_cursor'] ?? null) && $page['next_cursor'] !== '' ? $page['next_cursor'] : null;
        } while ($cursor !== null);

        return $items;
    }

    private function assertOk(int $status, string $path): void {
        if ($status >= 400) {
            // Nur Statuscode + Pfad — nie Payload/Token in Fehlermeldungen.
            throw new RuntimeException(sprintf('Todoist API %s antwortete mit HTTP %d.', $path, $status));
        }
    }
}
