<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TogglApi.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Toggl\Api;

use App\Plugins\Support\RemoteTimeWriter;
use App\Plugins\Toggl\Enums\TogglSource;
use App\Plugins\Toggl\Exceptions\TogglApiException;
use App\Plugins\Toggl\Sources\TogglEntry;
use Carbon\CarbonImmutable;

/**
 * Was Import, Rückschreiben und Übertragung von einem Toggl-Produkt brauchen
 * (Feature 178): {@see TogglApiClient} spricht Track, {@see TogglFocusApiClient}
 * das neue Toggl. Die Rückgabeformen sind für beide gleich; die Eintragsschlüssel
 * tragen das Präfix der {@see source()}.
 */
interface TogglApi extends RemoteTimeWriter {
    public function source(): TogglSource;

    public function isConfigured(): bool;

    /**
     * Waren alle Abrufe dieser Instanz vollständig? Nur dann darf der Import
     * auf drüben gelöschte Einträge schließen.
     */
    public function isFetchComplete(): bool;

    public function ping(): bool;

    /**
     * Eigene Einträge des Zugangs-Inhabers im Fenster (ergänzt die Workspace-Abfrage).
     *
     * @return array<int, TogglEntry>
     */
    public function fetchEntries(CarbonImmutable $from, CarbonImmutable $to): array;

    /**
     * Aktueller Fremdstand eines Eintrags für die Konflikt-Ansicht.
     *
     * @return array{status: 'ok'|'missing'|'error', entry: array<string, mixed>|null}
     */
    public function fetchEntry(int $id, ?int $workspaceId = null): array;

    /** @return array<int, array{id: int, name: string}> */
    public function workspaces(): array;

    /** @return array<int, array{id: int, name: string, archived: bool}> */
    public function workspaceClients(int $workspaceId): array;

    /** @return array<int, array{id: int, name: string, client_id: ?int, client_name: ?string, color: ?string, billable: bool, active: bool, start_date: ?string}> */
    public function workspaceProjects(int $workspaceId): array;

    /** @return array<int, array{id: ?int, email: string, name: string, timezone: ?string}> */
    public function workspaceUsers(int $workspaceId): array;

    /**
     * Einträge aller Benutzer des Workspaces; ohne Fenster die ganze Historie.
     *
     * @return array<int, TogglEntry>
     */
    public function workspaceEntries(int $workspaceId, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null): array;

    /**
     * Legt einen Eintrag für den Zugangs-Inhaber an. Der Datensatz trägt
     * `id`, `start`, `stop`, `description`, `project_id` und `billable`.
     *
     * @param  list<string>  $tags
     * @return array<string, mixed>
     *
     * @throws TogglApiException bei Nicht-2xx
     */
    public function createTimeEntry(
        int $workspaceId,
        CarbonImmutable $start,
        CarbonImmutable $stop,
        string $description,
        ?int $projectId,
        bool $billable,
        array $tags = [],
    ): array;
}
