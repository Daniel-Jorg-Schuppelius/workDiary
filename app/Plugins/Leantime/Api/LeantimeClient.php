<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LeantimeClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Leantime\Api;

use APIToolkit\API\Authentication\ApiKeyAuthentication;
use App\Plugins\Leantime\Exceptions\{LeantimeApiException, LeantimeVersionUnsupportedException};
use App\Plugins\Leantime\LeantimePlugin;
use App\Plugins\Support\{CallsJsonRpc, GuardsPluginApiResponses, PluginApiClient, PluginHttpFactory};
use App\Plugins\Support\TaskSync\IncompleteRemoteReadException;

/**
 * Lesezugriff auf die JSON-RPC-API von Leantime ab 3.8 (`/api/jsonrpc`,
 * Methoden `leantime.rpc.<Modul>.<Methode>`). Der API-Schlüssel sieht nur die
 * Projekte, denen er zugeordnet ist; `Tickets.getAll` liefert alle Tickets
 * eines Projekts in einer Antwort, ohne Seiten.
 */
class LeantimeClient {
    use CallsJsonRpc;
    use GuardsPluginApiResponses;

    private ?PluginApiClient $api = null;

    public function __construct(
        private readonly PluginHttpFactory $http,
        private readonly string $baseUrl,
        private readonly string $apiKey,
    ) {}

    /**
     * Leantime nennt dem Schlüssel weder Konto noch Version; die Prüfung ist das Lesen der Projekte.
     *
     * @return array{id: ?string, name: ?string, version: ?string}
     */
    public function account(): array {
        $this->projects();

        return ['id' => null, 'name' => null, 'version' => null];
    }

    /** @return list<array{id: string, key: string, name: string}> */
    public function projects(): array {
        try {
            $result = $this->call('Projects.getProjectsUserHasAccessTo');
        } catch (LeantimeApiException $e) {
            // Bis 3.7 ist `userId` Pflicht („Invalid params“); ab 3.8 gilt der Benutzer des Schlüssels.
            throw $e->errorCode === '-32602' ? new LeantimeVersionUnsupportedException('Leantime ab Version 3.8 erforderlich.', 0, $e) : $e;
        }
        // `false` heißt in Leantime „keine Projekte“.
        if ($result === false) {
            return [];
        }
        $projects = [];
        foreach ($this->rows($result) as $project) {
            $id = is_array($project) && is_scalar($project['id'] ?? null) ? (string) $project['id'] : '';
            if (ctype_digit($id) && is_string($project['name'] ?? null)) {
                $projects[] = ['id' => $id, 'key' => $id, 'name' => $project['name']];
            }
        }

        return $projects;
    }

    /**
     * Statusart (NEW, INPROGRESS, DONE) je Statusnummer eines Projekts.
     *
     * @return array<string, string>
     */
    public function statusTypes(string $projectId): array {
        $labels = $this->call('Tickets.getStatusLabels', ['projectId' => $this->projectId($projectId)]);
        if (! is_array($labels)) {
            throw new IncompleteRemoteReadException('invalid_response');
        }
        $types = [];
        foreach ($labels as $status => $label) {
            if (is_array($label) && is_string($label['statusType'] ?? null)) {
                $types[(string) $status] = strtoupper($label['statusType']);
            }
        }

        return $types;
    }

    /**
     * Alle Tickets eines Projekts samt archivierten (`status: all`; ohne den
     * Filter blendet Leantime Status -1 aus). Meilensteine filtert der Aufrufer:
     * `excludeType` schlösse auch Tickets ohne Typ aus (`NULL <> 'milestone'`).
     *
     * @return list<mixed>
     */
    public function tickets(string $projectId): array {
        $result = $this->call('Tickets.getAll', ['searchCriteria' => ['currentProject' => $this->projectId($projectId), 'status' => 'all']]);

        return $result === false ? throw new IncompleteRemoteReadException('invalid_response') : $this->rows($result);
    }

    /**
     * Einzelnes Ticket; null, wenn es fehlt, der Schlüssel das Projekt nicht
     * sieht (`false`) oder seine Rolle Tickets nicht lesen darf (-32001).
     *
     * @return array<string, mixed>|null
     */
    public function ticket(string $ticketId): ?array {
        if (! ctype_digit($ticketId)) {
            return null;
        }
        try {
            $ticket = $this->call('Tickets.getTicket', ['id' => (int) $ticketId]);
        } catch (LeantimeApiException $e) {
            if ($e->errorCode === '-32001') {
                return null;
            }

            throw $e;
        }

        /** @var array<string, mixed>|null */
        return is_array($ticket) ? $ticket : null;
    }

    /** Leseprobe der Instanz (Healthcheck). */
    public function probe(): void {
        $this->call('Projects.getProjectsUserHasAccessTo');
    }

    /** @return list<mixed> */
    private function rows(mixed $result): array {
        if (! is_array($result)) {
            throw new IncompleteRemoteReadException('invalid_response');
        }

        return array_values($result);
    }

    private function projectId(string $projectId): int {
        if (! ctype_digit($projectId)) {
            throw new IncompleteRemoteReadException('invalid_project');
        }

        return (int) $projectId;
    }

    /** @param array<string, mixed> $params */
    private function call(string $method, array $params = []): mixed {
        return $this->jsonRpc($this->api(), $this->baseUrl . '/api/jsonrpc', 'leantime.rpc.' . $method, $params);
    }

    private function api(): PluginApiClient {
        if ($this->api === null) {
            $this->api = $this->http->client(LeantimePlugin::ID, $this->baseUrl);
            $this->api->setAuthentication(new ApiKeyAuthentication($this->apiKey, 'x-api-key'));
        }

        return $this->api;
    }

    /** @return class-string<\App\Plugins\Support\PluginApiException> */
    protected function apiExceptionClass(): string {
        return LeantimeApiException::class;
    }

    protected function apiLabel(): string {
        return 'Leantime';
    }
}
