<?php
/*
 * Created on   : Tue Jun 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ApiWorkspaceSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Toggl\Sources;

use Carbon\CarbonImmutable;

/**
 * Workspace-Quelle aus der Toggl-API. Bindet einen {@see TogglApiClient} an
 * eine konkrete Workspace-ID und ein optionales Zeitfenster für die
 * Zeiteinträge (leer = vollständige Historie). Stammdaten kommen aus der
 * Track-API v9, die Zeiteinträge aus der Reports-API v3 (alle Benutzer).
 */
final class ApiWorkspaceSource implements WorkspaceSourceInterface {
    public function __construct(
        private readonly TogglApiClient $client,
        private readonly int $workspaceId,
        private readonly ?CarbonImmutable $from = null,
        private readonly ?CarbonImmutable $to = null,
    ) {}

    public function clients(): array {
        return $this->client->workspaceClients($this->workspaceId);
    }

    public function projects(): array {
        return $this->client->workspaceProjects($this->workspaceId);
    }

    public function users(): array {
        return $this->client->workspaceUsers($this->workspaceId);
    }

    public function entries(): array {
        return $this->client->workspaceEntries($this->workspaceId, $this->from, $this->to);
    }
}
