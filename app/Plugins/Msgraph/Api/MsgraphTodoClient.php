<?php
/*
 * Created on   : Thu Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphTodoClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Msgraph\Api;

use APIToolkit\API\Authentication\OAuth2\OAuth2BearerAuthentication;
use App\Models\MsgraphTaskConnection;
use App\Plugins\Msgraph\{MsgraphConfig, MsgraphPlugin};
use App\Plugins\Support\{ConnectionTokenStore, PluginApiClient, PluginHttpFactory};
use App\Services\CloudIntake\StaleCheckpointException;
use RuntimeException;
use Throwable;

/**
 * Microsoft-To-Do-Gateway (Feature 102, Schnitt E) auf dem
 * `php-api-toolkit`-Fundament: OAuth2-Bearer über den org-gebundenen
 * {@see ConnectionTokenStore} inkl. transparentem Refresh.
 *
 * - Listen: `GET /me/todo/lists` (Paging via `@odata.nextLink`).
 * - Aufgaben: `GET /me/todo/lists/{id}/tasks` — VOLLSTÄNDIGE Sicht; für
 *   Folgeläufe {@see tasksDelta()} (nur Änderungen inkl. `@removed`).
 * - Anlegen mit `linkedResources` (Rückverweis in die WorkDiary-Aufgabe);
 *   Ändern per PATCH, 404 = remote gelöscht (Aufrufer entscheidet).
 */
class MsgraphTodoClient implements GraphSubscriptionClient {
    use Concerns\ManagesGraphSubscriptions;

    private PluginApiClient $api;

    private string $base;

    public function __construct(private readonly MsgraphTaskConnection $connection) {
        $this->base = MsgraphConfig::resolve()['api_base'];
        $this->api = app(PluginHttpFactory::class)->client(MsgraphPlugin::ID, $this->base);

        // Org der Verbindung explizit (Variante B: per-Org-App, queue-sicher).
        $orgId = (int) $connection->organization_id;
        $grant = MsgraphConfig::isConfigured($orgId) ? app(MsgraphTasksOAuth::class)->grantFor($orgId) : null;
        $this->api->setAuthentication(new OAuth2BearerAuthentication(new ConnectionTokenStore($this->connection), $grant));
    }

    /**
     * To-Do-Listen des Kontos (für die Zuordnungs-Auswahl).
     *
     * @return list<array{id: string, name: string}>
     */
    public function lists(): array {
        $out = [];
        foreach ($this->graphPages($this->base . '/me/todo/lists', ['$top' => '100'], 'Graph /me/todo/lists') as $row) {
            if (is_array($row) && is_string($row['id'] ?? null) && $row['id'] !== '') {
                $out[] = ['id' => $row['id'], 'name' => (string) ($row['displayName'] ?? $row['id'])];
            }
        }

        return $out;
    }

    /**
     * Alle Aufgaben einer Liste (vollständige Sicht, Paging aufgelöst).
     *
     * @return list<array<string, mixed>>
     */
    public function tasks(string $listId): array {
        $out = [];
        foreach ($this->graphPages($this->base . '/me/todo/lists/' . rawurlencode($listId) . '/tasks', ['$top' => '100'], 'Graph todo tasks') as $row) {
            if (is_array($row)) {
                /** @var array<string, mixed> $row */
                $out[] = $row;
            }
        }

        return $out;
    }

    /**
     * Alle Elemente über @odata.nextLink-Seiten (Vollscan 2026-08-23, C3):
     * CursorPaginator mit Endlos-Guard und Seiten-Deckel; der „Cursor" ist
     * der nextLink (trägt die Query-Parameter bereits selbst).
     *
     * @param  array<string, string>  $query
     * @return \Generator<int, mixed>
     */
    private function graphPages(string $firstUrl, array $query, string $label): \Generator {
        $paginator = new \APIToolkit\API\Pagination\CursorPaginator(function (?string $nextLink) use ($firstUrl, $query, $label): \APIToolkit\API\Pagination\CursorPage {
            $response = $nextLink === null
                ? $this->api->getResponse($firstUrl, $query)
                : $this->api->getResponse($nextLink);
            if (! $response->successful()) {
                throw new RuntimeException($label . ' fehlgeschlagen (HTTP ' . $response->status() . ').');
            }
            $next = $response->json('@odata.nextLink');

            return new \APIToolkit\API\Pagination\CursorPage(
                (array) $response->json('value', []),
                is_string($next) && $next !== '' ? $next : null,
            );
        }, maxPages: 500);

        yield from $paginator;
    }

    /**
     * Delta-Seite der Aufgaben einer Liste (Feature 102, Folgeausbau): erste
     * Seite ohne Checkpoint = vollständige Sicht inkl. finalem Delta-Token;
     * Folge-Aufrufe über die absolute Checkpoint-URL liefern nur Änderungen —
     * gelöschte Aufgaben als `@removed`-Einträge. 410 Gone (Token abgelaufen)
     * ⇒ {@see StaleCheckpointException}, der Aufrufer startet voll neu.
     *
     * @return array{items: list<array<string, mixed>>, checkpoint: string, hasMore: bool}
     */
    public function tasksDelta(string $listId, ?string $checkpoint): array {
        if ($checkpoint === null || $checkpoint === '') {
            $response = $this->api->getResponse($this->base . '/me/todo/lists/' . rawurlencode($listId) . '/tasks/delta', ['$top' => '100']);
        } else {
            $response = $this->api->getResponse($checkpoint); // absolute next-/deltaLink-URL
        }

        if ($response->status() === 410) {
            throw new StaleCheckpointException('To-Do-Delta-Token abgelaufen (410 Gone).');
        }
        if (! $response->successful()) {
            throw new RuntimeException('Graph todo tasks/delta fehlgeschlagen (HTTP ' . $response->status() . ').');
        }

        /** @var array{value?: list<array<string, mixed>>, '@odata.nextLink'?: string, '@odata.deltaLink'?: string} $data */
        $data = (array) $response->json();

        // Direkt aus dem Array — json('@odata.…') würde die Punkte als
        // Pfad-Notation deuten (Intake-Client-Muster).
        $nextLink = isset($data['@odata.nextLink']) ? (string) $data['@odata.nextLink'] : null;
        $deltaLink = isset($data['@odata.deltaLink']) ? (string) $data['@odata.deltaLink'] : null;

        return [
            'items' => $data['value'] ?? [],
            'checkpoint' => (string) ($nextLink ?? $deltaLink ?? ''),
            'hasMore' => $nextLink !== null && $nextLink !== '',
        ];
    }

    /**
     * Legt eine Aufgabe an; Rückgabe = Graph-Task-ID.
     *
     * @param  array<string, mixed>  $payload
     */
    public function createTask(string $listId, array $payload): string {
        $response = $this->api->postJson($this->base . '/me/todo/lists/' . rawurlencode($listId) . '/tasks', $payload);
        if (! $response->successful()) {
            throw new RuntimeException('Graph POST todo task fehlgeschlagen (HTTP ' . $response->status() . ').');
        }

        $id = $response->json('id');
        if (! is_string($id) || $id === '') {
            throw new RuntimeException('Graph-To-Do-Task ohne ID angelegt.');
        }

        return $id;
    }

    /**
     * Aktualisiert eine Aufgabe; false = 404 (remote gelöscht).
     *
     * @param  array<string, mixed>  $payload
     */
    public function updateTask(string $listId, string $taskId, array $payload): bool {
        $response = $this->api->requestResponse('patch', $this->base . '/me/todo/lists/' . rawurlencode($listId) . '/tasks/' . rawurlencode($taskId), [
            'json' => $payload,
        ]);
        if ($response->status() === 404) {
            return false;
        }
        if (! $response->successful()) {
            throw new RuntimeException('Graph PATCH todo task fehlgeschlagen (HTTP ' . $response->status() . ').');
        }

        return true;
    }

    /**
     * Bestätigte Kontoidentität (`GET /me`) für das Admin-Panel.
     *
     * @return array{id: string, label: string}
     */
    public function account(): array {
        $response = $this->api->getResponse($this->base . '/me');
        if (! $response->successful()) {
            throw new RuntimeException('Graph /me fehlgeschlagen (HTTP ' . $response->status() . ').');
        }

        /** @var array{id?: string, displayName?: string, mail?: string, userPrincipalName?: string} $data */
        $data = (array) $response->json();

        return [
            'id' => (string) ($data['id'] ?? ''),
            'label' => trim((string) ($data['displayName'] ?? '') . ' <' . (string) ($data['mail'] ?? $data['userPrincipalName'] ?? '') . '>'),
        ];
    }

    /** Liveness/Auth-Check: Listen erreichbar. */
    public function ping(): bool {
        try {
            return $this->api->getResponse($this->base . '/me/todo/lists', ['$top' => '1', '$select' => 'id'])->successful();
        } catch (Throwable) {
            return false;
        }
    }
}
