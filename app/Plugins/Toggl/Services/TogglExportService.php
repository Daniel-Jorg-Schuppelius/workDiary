<?php
/*
 * Created on   : Fri Jul 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TogglExportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Toggl\Services;

use App\Enums\Integration\IntegrationInboxStatus;
use App\Models\Integration\{ExternalReference, IntegrationInboxItem};
use App\Models\Platform\Organization;
use App\Models\Project\Project;
use App\Models\Time\TimeEntry;
use App\Plugins\Support\{AbstractTimeEntryPushService, MatchingTimeImportService, RemoteTimeFingerprint};
use App\Plugins\Toggl\Api\{TogglApi, TogglClientFactory};
use App\Plugins\Toggl\Enums\TogglSource;
use App\Plugins\Toggl\Exceptions\TogglApiException;
use App\Plugins\Toggl\Support\TogglQuotaGuard;
use App\Plugins\Toggl\TogglPlugin;
use App\Support\MorphMap;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Überträgt in workDiary erfasste Zeiten (z. B. AnyDesk-/RemoteSupport-
 * Sitzungen) nach Toggl ({@see AbstractTimeEntryPushService}-Skelett).
 *
 * Anders als Kimai/OpenProject ist das SPIEGELUNG, kein „Rückbuchen":
 * {@see mirrorsEntries()} — die Einträge bleiben lokal abrechenbar
 * (`exported` bleibt false) und über die zusätzlich geschriebene
 * `entry`-Reference writeback-fähig. Der Duplikat-Schutz gegen das
 * Import-Echo des stündlichen Syncs: nach dem POST wird neben der
 * `pushed_entry`-Reference eine `entry`-Reference mit dem Schlüssel der
 * gewählten Quelle (`toggl:<id>` bzw. `focus:<id>`) und dem Fingerabdruck aus
 * dem POST-Response geschrieben — der nächste Import erkennt den Eintrag als
 * bekannt und unverändert. Kandidaten sind Work-Einträge mit echten
 * Start-/Endzeiten gemappter Projekte OHNE bestehende Toggl-Referenz (gleich
 * welcher Quelle — sonst legte ein Quellenwechsel alles noch einmal an).
 * Angelegt wird immer für den Zugangs-Inhaber.
 *
 * Nachtrag 2026-11-04 (Prod-Befund: Fernwartungs-Zeiten erreichten Toggl nie,
 * weil das Standardprojekt des Kunden keine Toggl-Projekt-ID hatte und der
 * Create-Pfad still verwarf): vor jedem Lauf werden die IDs täglich
 * nachgefüllt; ein Projekt ohne Gegenstück landet als Hinweis in der
 * Integrations-Inbox, und mit `export_create_projects` legt die Übertragung
 * Kunde und Projekt in Toggl an (am Namen erkannt, sonst neu).
 */
class TogglExportService extends AbstractTimeEntryPushService {
    private ?TogglApi $client = null;

    /** @var array<int, int> workDiary-Projekt-ID → Toggl-Projekt-ID */
    private array $projectMap = [];

    /** @var array<int, int> Toggl-Projekt-ID → Workspace-ID */
    private array $workspaceByTogglProject = [];

    private ?int $fallbackWorkspaceId = null;

    private bool $createProjects = false;

    /** @var array<int, array<int, array{id: int, name: string, archived: bool}>> Workspace → Toggl-Kunden (je Lauf gelesen) */
    private array $remoteClients = [];

    /** @var array<int, array<int, array{id: int, name: string, client_id: ?int, client_name: ?string, color: ?string, billable: bool, active: bool, start_date: ?string}>> */
    private array $remoteProjects = [];

    /** @var list<int>|null */
    private ?array $workspaceIds = null;

    /** Läuft gerade der Stunden-Batch? Nur er füllt die IDs nach — die Outbox-Anlage bleibt schlank. */
    private bool $batchRun = false;

    protected function pluginId(): string {
        return TogglPlugin::ID;
    }

    protected function prepareExport(Organization $organization, array $config): ?string {
        if (! (bool) ($config['export_enabled'] ?? false)) {
            return (string) __('Toggl-Übertragung ist deaktiviert (Plugin-Einstellung „Zeit-Übertragung aktivieren").');
        }

        if (TogglQuotaGuard::isPaused((int) $organization->id)) {
            return (string) __('Toggl-Stunden-Quota erschöpft — Übertragung pausiert bis zum Reset.');
        }

        $this->client = app(TogglClientFactory::class)->for((int) $organization->id);
        if (! $this->client->isConfigured()) {
            return $this->client->source()->missingAccessMessage();
        }

        $this->createProjects = (bool) ($config['export_create_projects'] ?? false);
        $this->remoteClients = [];
        $this->remoteProjects = [];
        $this->workspaceIds = null;

        // IDs der namensbasiert verknüpften Projekte täglich nachfüllen, bevor
        // die Projektkarte gelesen wird (sonst fehlen sie dem Push für immer).
        if ($this->batchRun) {
            app(TogglImportService::class)->backfillIdReferencesDaily($organization, $this->client);
        }

        $this->projectMap = $this->togglProjectIdByProjectId($organization, $this->client->source());

        $this->resolveWorkspaces((int) $organization->id, $config);
        if ($this->fallbackWorkspaceId === null && $this->workspaceByTogglProject === [] && $this->creationWorkspace() === null) {
            return (string) __('Kein Toggl-Workspace auflösbar (Workspace-ID in den Plugin-Einstellungen hinterlegen).');
        }

        return null;
    }

    /**
     * Stunden-Batch: vor dem Lauf die stabilen IDs nachfüllen (einmal je Tag).
     *
     * @param  array<string, mixed>  $config
     * @return array{pushed: int, skipped: int, failed: int, errors: list<string>}
     */
    public function exportPending(Organization $organization, array $config, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null): array {
        $this->batchRun = true;
        try {
            return parent::exportPending($organization, $config, $from, $to);
        } finally {
            $this->batchRun = false;
        }
    }

    /**
     * Alle Projekte der Organisation sind Kandidaten: ein Projekt ohne
     * Toggl-ID sucht sein Gegenstück am Namen oder legt es an
     * ({@see ensureProjectMapping()}); bleibt es ohne, steht es als Hinweis
     * in der Inbox statt still aus der Kandidatenliste zu fallen.
     */
    protected function exportableProjectIds(Organization $organization): array {
        return array_values(array_map(
            static fn ($id): int => (int) $id,
            Project::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->pluck('id')->all(),
        ));
    }

    protected function mirrorsEntries(): bool {
        return true;
    }

    protected function shouldSkip(Organization $organization, TimeEntry $entry): bool {
        $projectId = (int) $entry->project_id;
        if (! isset($this->projectMap[$projectId]) && ! $this->ensureProjectMapping($organization, $projectId)) {
            return true;
        }

        return $this->workspaceFor($projectId) === null
            || $entry->started_at === null
            || $entry->ended_at === null;
    }

    /**
     * Gegenstück des Projekts in Toggl finden oder anlegen: Kunde über seine
     * ID-Referenz oder den Namen, Projekt über Kunde + Name; fehlt es und ist
     * die Anlage erlaubt, entsteht es. Sonst (oder bei einem API-Fehler)
     * bleibt der Eintrag liegen und das Projekt steht als Hinweis in der
     * Integrations-Inbox — der Verwurf war bisher unsichtbar.
     */
    private function ensureProjectMapping(Organization $organization, int $projectId): bool {
        assert($this->client instanceof TogglApi);

        $project = Project::query()
            ->withoutGlobalScopes()
            ->with(['customer', 'foreignCustomer'])
            ->whereKey($projectId)
            ->where('organization_id', $organization->id)
            ->first();
        if (! $project instanceof Project) {
            return false;
        }

        $clientTarget = $project->foreignCustomer ?? $project->customer;
        $clientName = $clientTarget !== null ? trim((string) $clientTarget->name) : '';
        $clientName = $clientName !== '' ? $clientName : null;

        $workspaceId = $this->creationWorkspace();
        if ($workspaceId === null) {
            $this->noteUnmappedProject($organization, $project, $clientName, (string) __('Kein Toggl-Workspace auflösbar (Workspace-ID in den Plugin-Einstellungen hinterlegen).'));

            return false;
        }

        try {
            $togglClientId = $clientTarget !== null && $clientName !== null
                ? $this->resolveRemoteClient($organization, $workspaceId, $clientTarget, $clientName)
                : null;
            $togglProjectId = $this->resolveRemoteProject($workspaceId, $clientName, trim((string) $project->name), $togglClientId);
        } catch (TogglApiException $e) {
            $this->noteUnmappedProject($organization, $project, $clientName, $e->getMessage());

            return false;
        }

        if ($togglProjectId === null) {
            $this->noteUnmappedProject($organization, $project, $clientName);

            return false;
        }

        app(TogglImportService::class)->rememberProjectMapping($organization, $this->client->source(), $project, $togglProjectId, $clientName, $togglClientId);
        $this->projectMap[$projectId] = $togglProjectId;
        $this->workspaceByTogglProject[$togglProjectId] = $workspaceId;

        return true;
    }

    /** Toggl-Kunde des Kunden/Endkunden: gemerkte ID, sonst Namenssuche, sonst Anlage (wenn erlaubt). */
    private function resolveRemoteClient(Organization $organization, int $workspaceId, \Illuminate\Database\Eloquent\Model $clientTarget, string $clientName): ?int {
        assert($this->client instanceof TogglApi);

        $known = ExternalReference::query()
            ->forPlugin($organization, TogglPlugin::ID, $this->client->source()->clientIdType())
            ->forReferenceable($clientTarget)
            ->value('external_id');
        if (is_numeric($known)) {
            return (int) $known;
        }

        foreach ($this->remoteClients($workspaceId) as $remoteClient) {
            if (mb_strtolower($remoteClient['name']) === mb_strtolower($clientName)) {
                return $remoteClient['id'];
            }
        }

        if (! $this->createProjects) {
            return null;
        }

        $created = $this->client->createClient($workspaceId, $clientName);
        $this->remoteClients[$workspaceId][] = ['id' => $created['id'], 'name' => $created['name'], 'archived' => false];

        return $created['id'];
    }

    /** Toggl-Projekt zu Kunde + Name: Namenssuche im Workspace, sonst Anlage (wenn erlaubt). */
    private function resolveRemoteProject(int $workspaceId, ?string $clientName, string $projectName, ?int $togglClientId): ?int {
        assert($this->client instanceof TogglApi);

        if ($projectName === '') {
            return null;
        }

        foreach ($this->remoteProjects($workspaceId) as $remoteProject) {
            if (mb_strtolower($remoteProject['name']) !== mb_strtolower($projectName)) {
                continue;
            }
            // Gleicher Name reicht nicht: „Wartung“ gibt es je Kunde — der Kunde muss passen.
            $sameClient = $togglClientId !== null
                ? $remoteProject['client_id'] === $togglClientId
                : ($remoteProject['client_id'] === null || ($clientName !== null && mb_strtolower((string) $remoteProject['client_name']) === mb_strtolower($clientName)));
            if ($sameClient) {
                return $remoteProject['id'];
            }
        }

        if (! $this->createProjects) {
            return null;
        }

        $created = $this->client->createProject($workspaceId, $projectName, $togglClientId);
        $this->remoteProjects[$workspaceId][] = [
            'id' => $created['id'], 'name' => $created['name'], 'client_id' => $created['client_id'], 'client_name' => $clientName,
            'color' => null, 'billable' => false, 'active' => true, 'start_date' => null,
        ];

        return $created['id'];
    }

    /** @return array<int, array{id: int, name: string, archived: bool}> */
    private function remoteClients(int $workspaceId): array {
        assert($this->client instanceof TogglApi);

        return $this->remoteClients[$workspaceId] ??= $this->client->workspaceClients($workspaceId);
    }

    /** @return array<int, array{id: int, name: string, client_id: ?int, client_name: ?string, color: ?string, billable: bool, active: bool, start_date: ?string}> */
    private function remoteProjects(int $workspaceId): array {
        assert($this->client instanceof TogglApi);

        return $this->remoteProjects[$workspaceId] ??= $this->client->workspaceProjects($workspaceId);
    }

    /** Workspace für neue Gegenstücke: die konfigurierte ID, sonst der einzige bzw. erste des Zugangs. */
    private function creationWorkspace(): ?int {
        assert($this->client instanceof TogglApi);

        if ($this->fallbackWorkspaceId !== null) {
            return $this->fallbackWorkspaceId;
        }
        $this->workspaceIds ??= array_values(array_map(static fn (array $w): int => (int) $w['id'], $this->client->workspaces()));

        return $this->workspaceIds[0] ?? null;
    }

    /**
     * Ein Projekt ohne Gegenstück als Hinweis in die Integrations-Inbox —
     * genau eine Zeile je Projekt (`iii_dedupe_unique`): ein verworfener
     * Hinweis kommt nicht wieder, ein offener wird fortgeschrieben, ein
     * erledigter beim nächsten Fehlen wieder geöffnet.
     */
    private function noteUnmappedProject(Organization $organization, Project $project, ?string $clientName, ?string $error = null): void {
        $dedupeKey = TogglPlugin::ID . '-project-unmapped:' . $project->getKey();
        $existing = IntegrationInboxItem::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', TogglPlugin::ID)
            ->where('dedupe_key', $dedupeKey)
            ->first();
        if ($existing !== null && $existing->status === IntegrationInboxStatus::Dismissed) {
            return;
        }

        // Die Inbox zeigt vom lokalen Stand nur Zeit, Minuten und Beschreibung — der Rat steht deshalb in der Beschreibung.
        $hint = $this->createProjects
            ? (string) __('Anlage in Toggl fehlgeschlagen, der nächste Lauf versucht es erneut.')
            : (string) __('Projekt in Toggl anlegen oder „Fehlende Toggl-Projekte anlegen“ einschalten.');
        $snapshot = [
            'reason' => IntegrationInboxItem::REASON_PROJECT_UNMAPPED,
            'resolution' => IntegrationInboxItem::RESOLUTION_ACKNOWLEDGE_ONLY,
            'project' => (string) $project->name,
            'customer' => $clientName,
            'local' => ['description' => $error !== null && $error !== '' ? $hint . ' (' . $error . ')' : $hint],
        ];
        $attributes = [
            'display_subtitle' => $clientName !== null ? $clientName . ' · ' . $project->name : (string) $project->name,
            'remote_snapshot' => $snapshot,
            'occurred_at' => now(),
        ];

        if ($existing !== null) {
            $existing->forceFill($attributes + ['status' => IntegrationInboxStatus::Open, 'resolved_at' => null, 'resolved_by' => null])->save();

            return;
        }

        IntegrationInboxItem::query()->withoutGlobalScopes()->create($attributes + [
            'organization_id' => $organization->id,
            'plugin_id' => TogglPlugin::ID,
            'dedupe_key' => $dedupeKey,
            'source' => TogglPlugin::ID,
            'target_type' => MorphMap::alias(Project::class),
            'external_type' => MatchingTimeImportService::EXT_TYPE_PROJECT,
            'external_id' => (string) $project->getKey(),
            'case_type' => IntegrationInboxItem::CASE_CONFLICT,
            'referenceable_type' => $project->getMorphClass(),
            'referenceable_id' => $project->getKey(),
            'status' => IntegrationInboxStatus::Open,
        ]);
    }

    /** Nach der ersten Übertragung des Projekts ist der Hinweis erledigt. */
    private function resolveUnmappedNotice(Organization $organization, int $projectId): void {
        IntegrationInboxItem::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', TogglPlugin::ID)
            ->where('dedupe_key', TogglPlugin::ID . '-project-unmapped:' . $projectId)
            ->where('status', IntegrationInboxStatus::Open->value)
            ->update(['status' => IntegrationInboxStatus::ResolvedLinked->value, 'resolved_at' => now()]);
    }

    protected function createRemoteEntry(Organization $organization, TimeEntry $entry): string {
        assert($this->client instanceof TogglApi);

        $togglProjectId = $this->projectMap[(int) $entry->project_id];
        $workspaceId = (int) $this->workspaceFor((int) $entry->project_id);

        $record = $this->client->createTimeEntry(
            $workspaceId,
            CarbonImmutable::parse((string) $entry->started_at),
            CarbonImmutable::parse((string) $entry->ended_at),
            (string) ($entry->description ?? ''),
            $togglProjectId,
            (bool) $entry->billable,
            array_values($entry->tags->pluck('name')->map(fn ($name): string => (string) $name)->all()),
        );

        $id = is_numeric($record['id'] ?? null) ? (int) $record['id'] : null;
        if ($id === null) {
            throw new TogglApiException('Toggl-Antwort ohne Eintrags-ID');
        }

        $this->recordEntryReference($organization, $entry, $record, $workspaceId, $togglProjectId, $this->client->source()->entryKey($id));
        $this->resolveUnmappedNotice($organization, (int) $entry->project_id);

        return (string) $id;
    }

    /** Spiegelung statt Rückbuchung: lokal bleibt der Eintrag abrechenbar. */
    protected function isExpectedFailure(\Throwable $e): bool {
        return $e instanceof TogglApiException;
    }

    protected function shouldAbort(\Throwable $e): bool {
        return $e instanceof TogglApiException && $e->isRateLimited();
    }

    protected function pushedPayload(TimeEntry $entry): ?array {
        return [
            'workspace_id' => $this->workspaceFor((int) $entry->project_id),
            'minutes' => (int) $entry->minutes,
        ];
    }

    /**
     * `entry`-Reference im Format des Imports (Quellschlüssel + Fingerabdruck aus
     * dem POST-Response): der nächste stündliche Sync erkennt den gepushten
     * Eintrag als bekannt/unverändert (kein Duplikat, kein Konflikt), und der
     * Writeback kann spätere lokale Korrekturen adressieren.
     *
     * @param  array<string, mixed>  $record
     */
    private function recordEntryReference(Organization $organization, TimeEntry $entry, array $record, int $workspaceId, int $togglProjectId, string $entryKey): void {
        $start = is_string($record['start'] ?? null) ? $record['start'] : null;
        $stop = is_string($record['stop'] ?? null) ? $record['stop'] : null;
        $fingerprint = '';
        if ($start !== null && $stop !== null) {
            $fingerprint = RemoteTimeFingerprint::fromParts(
                CarbonImmutable::parse($start),
                CarbonImmutable::parse($stop),
                isset($record['description']) ? (string) $record['description'] : null,
                is_numeric($record['project_id'] ?? null) ? (int) $record['project_id'] : $togglProjectId,
                (bool) ($record['billable'] ?? false),
            );
        }

        ExternalReference::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'plugin_id' => TogglPlugin::ID,
                'external_type' => MatchingTimeImportService::EXT_TYPE_ENTRY,
                'referenceable_type' => $entry->getMorphClass(),
                'referenceable_id' => $entry->getKey(),
            ],
            [
                'external_id' => $entryKey,
                'payload' => [
                    'source' => 'push',
                    'project_id' => $togglProjectId,
                    'workspace_id' => $workspaceId,
                    // '' = Fingerabdruck nicht bestimmbar → syncKnownEntry
                    // behandelt den Eintrag als unverändert (safe).
                    'fingerprint' => $fingerprint,
                ],
                'synced_at' => now(),
            ],
        );
    }

    /**
     * Mapping workDiary-Projekt-ID → Toggl-Projekt-ID aus den Projekt-ID-
     * References des Imports — nur die der gewählten Quelle.
     *
     * @return array<int, int>
     */
    private function togglProjectIdByProjectId(Organization $organization, TogglSource $source): array {
        return ExternalReference::query()
            ->forPlugin($organization, TogglPlugin::ID, $source->projectIdType())
            ->where('referenceable_type', (new Project)->getMorphClass())
            ->pluck('external_id', 'referenceable_id')
            ->mapWithKeys(fn ($externalId, $projectId): array => [(int) $projectId => (int) $externalId])
            ->all();
    }

    /**
     * Workspace-Auflösung: konfigurierte workspace_id gewinnt; sonst über die
     * Workspace-Projektlisten des Tokens (Projekt → Workspace).
     *
     * @param  array<string, mixed>  $config
     */
    private function resolveWorkspaces(int $organizationId, array $config): void {
        assert($this->client instanceof TogglApi);

        if (is_numeric($config['workspace_id'] ?? null)) {
            $this->fallbackWorkspaceId = (int) $config['workspace_id'];

            return;
        }

        // Die API-Auflösung kostet 1+n Calls — kurz je Org cachen, damit nicht
        // jeder einzelne Outbox-Push sie erneut bezahlt (Toggl-Stunden-Quota).
        /** @var array{fallback: int|null, map: array<int, int>} $resolved */
        $resolved = Cache::remember('toggl:workspaces:' . $this->client->source()->value . ':' . $organizationId, 600, function (): array {
            assert($this->client instanceof TogglApi);

            $workspaces = $this->client->workspaces();
            if (count($workspaces) === 1) {
                return ['fallback' => (int) $workspaces[0]['id'], 'map' => []];
            }

            $map = [];
            foreach ($workspaces as $workspace) {
                $workspaceId = (int) $workspace['id'];
                foreach ($this->client->workspaceProjects($workspaceId) as $project) {
                    $map[(int) $project['id']] = $workspaceId;
                }
            }

            return ['fallback' => null, 'map' => $map];
        });

        $this->fallbackWorkspaceId = $resolved['fallback'];
        $this->workspaceByTogglProject = $resolved['map'];
    }

    private function workspaceFor(int $projectId): ?int {
        $togglProjectId = $this->projectMap[$projectId] ?? null;
        if ($togglProjectId === null) {
            return null;
        }

        return $this->workspaceByTogglProject[$togglProjectId] ?? $this->fallbackWorkspaceId;
    }
}
