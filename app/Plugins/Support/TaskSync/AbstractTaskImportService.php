<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AbstractTaskImportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\TaskSync;

use APIToolkit\Exceptions\UnauthorizedException;
use App\Enums\Integration\{DataDomain, IntegrationInboxStatus, TaskImportLinkStatus};
use App\Enums\Task\TaskStatus;
use App\Models\Integration\{ExternalReference, IntegrationInboxItem};
use App\Models\Platform\Organization;
use App\Models\Project\Task;
use App\Plugins\PluginErrorRecorder;
use App\Plugins\Support\{PluginApiException, PluginSettingsResolver, PluginTenantGate};
use App\Services\Integration\DataOwnershipResolver;
use App\Services\Licensing\FeatureFlagResolver;
use App\Support\Tz;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\{Collection as EloquentCollection, Model};
use Illuminate\Support\Facades\{Cache, DB};
use Throwable;

/**
 * Reiner Aufgabenimport (Jira, Asana; Feature 169) auf dem 3-Wege-Kern von
 * {@see AbstractTaskSyncService}: die Quelle liefert Titel, Beschreibung,
 * Status und Fälligkeit, WorkDiary führt alles andere. Die Adapter liefern
 * Lesen und Abbildung ({@see fetch()}, {@see normalize()}); Lauf, Sperre,
 * Datenführung, Klärfälle und Aufholpunkt stehen hier einmal.
 *
 * - Ein Lauf je Organisation und Anbieter (Cache-Sperre); das ausdrückliche
 *   Verknüpfen nimmt dieselbe Sperre.
 * - Neue Aufgaben nur in bestätigten Zuordnungen, nie erledigte Altaufgaben,
 *   nie bei fremder Datenführung oder mehrdeutigem Ziel.
 * - Bestand wird nie verschoben; ein anderes Ziel ist ein Klärfall.
 * - Ein lokaler Abschluss bleibt, bis die Quelle selbst abschließt.
 * - Konfliktentscheidungen der Inbox werden zur neuen Basis; ein späterer
 *   Fremdwechsel ergibt einen neuen Fall (Runde im Referenz-Payload).
 * - Der Aufholpunkt einer Zuordnung rückt nur nach vollständigem Lesen und
 *   fehlerfreier Verarbeitung vor; „nicht mehr sichtbar“ nur nach einem
 *   vollständigen Vollabgleich.
 *
 * @template TLink of Model&TaskImportLink
 * @template TConnection of Model&TaskImportConnection
 *
 * @extends AbstractTaskSyncService<TLink>
 */
abstract class AbstractTaskImportService extends AbstractTaskSyncService {
    /** Überlappung des Delta-Laufs mit dem letzten Aufholpunkt. */
    public const OVERLAP_MINUTES = 10;

    private const LOCK_SECONDS = 1800;

    /** Höchstzahl der Vorschauzeilen. */
    public const PREVIEW_LIMIT = 200;

    /** @return class-string<TConnection> */
    abstract protected function connectionModel(): string;

    /** @return class-string<TLink> */
    abstract protected function linkModel(): string;

    abstract protected function isConfigured(int $organizationId): bool;

    /**
     * Liest die Aufgaben einer Zuordnung: `$full` alle, sonst ohne `$since`
     * nur offene (Erstlauf), mit `$since` die seitdem geänderten. Wirft bei
     * Abbruch — eine unvollständige Seite gilt nie als leeres Ergebnis.
     *
     * @param  TConnection  $connection
     * @param  TLink  $link
     * @return iterable<array<string, mixed>>
     */
    abstract protected function fetch(Model $connection, Model $link, ?CarbonImmutable $since, bool $full): iterable;

    /**
     * Einzelabruf für das ausdrückliche Verknüpfen; null = in der Quelle nicht sichtbar.
     *
     * @param  TConnection  $connection
     * @param  TLink  $link
     * @return array<string, mixed>|null
     */
    abstract protected function fetchOne(Model $connection, Model $link, string $remoteId): ?array;

    /** @param array<string, mixed> $raw */
    abstract protected function normalize(array $raw, Organization $organization): RemoteTask;

    /** Sicherer Link zur Quellaufgabe aus dem Referenz-Payload (nur https auf dem Anbieter-Host). */
    abstract public function sourceUrl(ExternalReference $reference): ?string;

    /**
     * Meldet die Quelle einen Bearbeitungsstand? Kennt sie nur offen und
     * erledigt (Asana, Gitea, Redmine), bleibt ein lokales „in Bearbeitung“.
     */
    protected function reportsProgress(): bool {
        return true;
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @return array<string, mixed>
     */
    private function adjustMapped(array $mapped, Task $task): array {
        if (! $this->reportsProgress() && ($mapped['status'] ?? null) === TaskStatus::Open->value && $task->status === TaskStatus::InProgress) {
            $mapped['status'] = TaskStatus::InProgress->value;
        }

        return $mapped;
    }

    /**
     * Zeitpunkt der Quelle als Datum in der Zeitzone der Organisation;
     * null bleibt null, Unlesbares ist false (Feld bleibt unberührt).
     */
    protected function localDate(mixed $value, Organization $organization): string|false|null {
        if ($value === null) {
            return null;
        }
        if (! is_string($value) || trim($value) === '') {
            return false;
        }
        try {
            return CarbonImmutable::parse($value)->setTimezone(Tz::ofOrganization($organization))->toDateString();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Für Adapter ohne serverseitigen Änderungsfilter: ab dem Aufholpunkt
     * geändert? Ein unlesbarer Zeitstempel zählt als geändert — lieber einmal
     * zu viel vergleichen als still auslassen.
     */
    protected function changedSince(mixed $updated, CarbonImmutable $since): bool {
        if (! is_string($updated) || trim($updated) === '') {
            return true;
        }
        try {
            return CarbonImmutable::parse($updated)->greaterThanOrEqualTo($since);
        } catch (Throwable) {
            return true;
        }
    }

    /**
     * Gemeinsame Feldabbildung der Adapter: nur übergebene Schlüssel zählen
     * (fehlend ≠ gelöscht). `status` null ist ein unbekannter Status
     * (Klärfall), `due` ein Zeitpunkt über {@see localDate()}, `due_on` ein
     * reines Datum, das nie in eine Zeitzone umgerechnet wird.
     *
     * @param  array{title?: ?string, description?: ?string, status?: ?TaskStatus, due?: mixed, due_on?: ?string}  $values
     * @return array{0: array{title?: string, description?: ?string, status?: string, due_date?: ?string}, 1: list<string>}
     */
    protected function mapFields(array $values, Organization $organization): array {
        $mapped = [];
        $issues = [];
        if (array_key_exists('title', $values)) {
            $title = trim((string) $values['title']);
            if ($title === '') {
                $issues[] = RemoteTask::EMPTY_TITLE;
            } else {
                $mapped['title'] = mb_substr($title, 0, 255);
            }
        }
        if (array_key_exists('description', $values)) {
            $description = trim((string) $values['description']);
            $mapped['description'] = $description !== '' ? $description : null;
        }
        if (array_key_exists('status', $values)) {
            if ($values['status'] instanceof TaskStatus) {
                $mapped['status'] = $values['status']->value;
            } else {
                $issues[] = RemoteTask::UNKNOWN_STATUS;
            }
        }
        if (array_key_exists('due', $values)) {
            $due = $this->localDate($values['due'], $organization);
            if ($due !== false) {
                $mapped['due_date'] = $due;
            }
        }
        if (array_key_exists('due_on', $values)) {
            $dueOn = $values['due_on'];
            if ($dueOn === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueOn) === 1) {
                $mapped['due_date'] = $dueOn;
            }
        }

        return [$mapped, $issues];
    }

    // ── Lauf ─────────────────────────────────────────────────────────────

    /**
     * Abgleich aller bestätigten Zuordnungen einer Organisation — dieselbe
     * Strecke für Scheduler und manuellen Lauf.
     *
     * @return array{created: int, updated: int, unchanged: int, conflicts: int, inbox: int, failed: int}
     */
    public function run(Organization $organization, bool $full = false): array {
        return $this->locked((int) $organization->id, fn (): array => $this->runLocked($organization, $full));
    }

    /** @return TConnection|null */
    public function connection(int $organizationId): ?Model {
        $model = $this->connectionModel();

        return $model::query()->withoutGlobalScopes()->where('organization_id', $organizationId)->first();
    }

    /** Darf jetzt importiert werden? Prüft Plugin, Modul, Konfiguration und Verbindung am Ausführungszeitpunkt. */
    public function mayRun(Organization $organization): bool {
        $orgId = (int) $organization->id;
        if (PluginTenantGate::blocks($orgId) || ! PluginSettingsResolver::for($this->pluginId(), $orgId)->enabled()) {
            return false;
        }
        if (! app(FeatureFlagResolver::class)->isEnabled('module.kanban') || ! $this->isConfigured($orgId)) {
            return false;
        }

        return $this->connection($orgId)?->isReady() === true;
    }

    /** @return array{created: int, updated: int, unchanged: int, conflicts: int, inbox: int, failed: int} */
    private function runLocked(Organization $organization, bool $full): array {
        $counters = self::emptyCounters();
        if (! $this->mayRun($organization)) {
            return $counters;
        }
        $connection = $this->connection((int) $organization->id);
        if ($connection === null) {
            return $counters;
        }
        $links = $this->runnableLinks((int) $organization->id);
        if ($links->isEmpty()) {
            return $counters;
        }

        $startedAt = CarbonImmutable::now();
        $mayWrite = app(DataOwnershipResolver::class)->mayWrite($organization, DataDomain::Tasks, $this->pluginId());

        /** @var array<string, array{remote: RemoteTask, links: array<int, TLink>}> $seen */
        $seen = [];
        /** @var array<int, bool> $complete */
        $complete = [];
        /** @var array<int, string> $errors */
        $errors = [];
        /** @var array<int, array{created: int, updated: int, unchanged: int, conflicts: int, inbox: int, failed: int}> $linkCounters */
        $linkCounters = [];
        foreach ($links as $link) {
            $linkId = (int) $link->getKey();
            $complete[$linkId] = false;
            $linkCounters[$linkId] = self::emptyCounters();
            $since = $full ? null : $link->checkpointAt()?->subMinutes(self::OVERLAP_MINUTES);
            try {
                foreach ($this->fetch($connection, $link, $since, $full) as $raw) {
                    $remote = $this->normalize($raw, $organization);
                    $seen[$remote->id] ??= ['remote' => $remote, 'links' => []];
                    $seen[$remote->id]['remote'] = $remote;
                    $seen[$remote->id]['links'][$linkId] = $link;
                }
                $complete[$linkId] = true;
            } catch (Throwable $e) {
                $this->recordError($e, $organization, ['link' => $linkId]);
                if ($this->isAuthFailure($e)) {
                    $connection->recordConnectionFailure($this->errorLabel($e));

                    throw $e;
                }
                $errors[$linkId] = $this->errorLabel($e);
            }
        }

        foreach ($seen as $entry) {
            try {
                $outcome = DB::transaction(fn (): string => $this->importOne($organization, $connection, $entry['remote'], $entry['links'], $mayWrite));
            } catch (Throwable $e) {
                $outcome = 'failed';
                $this->recordError($e, $organization, ['external_id' => $entry['remote']->id]);
            }
            $counters = self::bump($counters, $outcome);
            foreach (array_keys($entry['links']) as $linkId) {
                $linkCounters[$linkId] = self::bump($linkCounters[$linkId], $outcome);
            }
        }

        $succeeded = static fn (int $linkId): bool => $complete[$linkId] && $linkCounters[$linkId]['failed'] === 0;
        if ($full && collect(array_keys($complete))->every($succeeded)) {
            $visible = collect(array_keys($seen))->map(fn (string $id): array => ['id' => $this->externalId($connection, $id)]);
            foreach ($links as $link) {
                $before = $counters['inbox'];
                $this->flagRemoteDeletions($link, $visible, $counters);
                $own = $linkCounters[(int) $link->getKey()];
                $own['inbox'] += $counters['inbox'] - $before;
                $linkCounters[(int) $link->getKey()] = $own;
            }
        }

        foreach ($links as $link) {
            $linkId = (int) $link->getKey();
            $link->recordRun($linkCounters[$linkId], $succeeded($linkId), $startedAt, $full, $errors[$linkId] ?? null);
        }
        $connection->markSynced($counters);
        $errors !== [] ? $connection->recordConnectionFailure((string) reset($errors)) : $connection->recordConnectionSuccess();
        $this->afterImport($organization, $connection, $links);

        return $counters;
    }

    /**
     * @param  TConnection  $connection
     * @param  array<int, TLink>  $links  Zuordnungen, in denen die Aufgabe gelesen wurde
     * @return 'created'|'updated'|'unchanged'|'conflicts'|'inbox'
     */
    private function importOne(Organization $organization, Model $connection, RemoteTask $remote, array $links, bool $mayWrite): string {
        $externalId = $this->externalId($connection, $remote->id);
        $snapshot = ['id' => $externalId] + $remote->snapshot;
        $targets = array_values(array_unique(array_map(fn (Model $l): int => $l->targetProjectId(), $links)));
        $reference = ExternalReference::query()
            ->forPlugin($organization, $this->pluginId(), $this->externalType())
            ->forExternalId($externalId)
            ->first();

        if ($reference === null) {
            $link = $this->linkFor($links, null);
            if (! $mayWrite) {
                return $this->stageInbox($link, $snapshot, 'ownership');
            }
            if ($remote->isDone()) {
                return 'unchanged'; // erledigte Historie wird nie neu angelegt
            }
            foreach ([RemoteTask::UNKNOWN_STATUS, RemoteTask::EMPTY_TITLE] as $issue) {
                if ($remote->has($issue)) {
                    return $this->stageInbox($link, $snapshot, $issue);
                }
            }
            if (count($targets) > 1) {
                return $this->stageInbox($link, $snapshot, 'ambiguous_target');
            }

            $this->create($organization, $link, $connection, $remote, $snapshot);

            return 'created';
        }

        $task = $reference->referenceable;
        $link = $this->linkFor($links, $task instanceof Task ? (int) $task->project_id : null);
        if (! $task instanceof Task) {
            return $this->stageInbox($link, $snapshot, 'local_deleted');
        }
        if (! $mayWrite) {
            return $this->stageInbox($link, $snapshot, 'ownership');
        }
        if (! in_array((int) $task->project_id, $targets, true)) {
            $this->stageInbox($link, $snapshot, 'target_mismatch');
        }

        return $this->applyRemote($link, $reference, $task, $remote, $snapshot);
    }

    /**
     * @param  TLink  $link
     * @param  array<string, mixed>  $snapshot
     * @return 'updated'|'unchanged'|'conflicts'
     */
    private function applyRemote(Model $link, ExternalReference $reference, Task $task, RemoteTask $remote, array $snapshot): string {
        $mapped = $remote->fields;
        foreach ([RemoteTask::UNKNOWN_STATUS => 'status', RemoteTask::EMPTY_TITLE => 'title', RemoteTask::DESCRIPTION_INCOMPLETE => 'description'] as $issue => $field) {
            if ($remote->has($issue)) {
                unset($mapped[$field]);
                $this->stageInbox($link, $snapshot, $issue);
            }
        }

        $payload = (array) ($reference->payload ?? []);
        // Lokaler Abschluss bleibt, bis die Quelle selbst abschließt — auch gegen „in Bearbeitung“.
        if (isset($mapped['status']) && $mapped['status'] !== TaskStatus::Done->value
            && $task->status === TaskStatus::Done && ($payload['done_origin'] ?? null) !== $this->doneOriginMarker()) {
            unset($mapped['status']);
        }
        $mapped = $this->adjustMapped($mapped, $task);

        $this->acknowledgeResolvedConflict($reference);

        return $this->applyThreeWay($link, $reference, $task, $snapshot, $mapped);
    }

    /**
     * @param  TLink  $link
     * @param  TConnection  $connection
     * @param  array<string, mixed>  $snapshot
     */
    private function create(Organization $organization, Model $link, Model $connection, RemoteTask $remote, array $snapshot): Task {
        $description = $remote->fields['description'] ?? null;
        if ($remote->has(RemoteTask::DESCRIPTION_INCOMPLETE)) {
            $description = trim((string) $description . "\n\n" . __('integration.task_import.description_incomplete'));
            $this->stageInbox($link, $snapshot, RemoteTask::DESCRIPTION_INCOMPLETE);
        }

        $task = Task::query()->create([
            'organization_id' => $organization->id,
            'project_id' => $link->targetProjectId(),
            'is_global' => false,
            'title' => (string) ($remote->fields['title'] ?? ''),
            'description' => $description,
            'status' => $remote->fields['status'] ?? TaskStatus::Open->value,
            'due_date' => $remote->fields['due_date'] ?? null,
        ]);

        ExternalReference::query()->create([
            'organization_id' => $organization->id,
            'plugin_id' => $this->pluginId(),
            'external_type' => $this->externalType(),
            'external_id' => (string) $snapshot['id'],
            'referenceable_type' => $task->getMorphClass(),
            'referenceable_id' => $task->getKey(),
            'synced_at' => now(),
            'payload' => [
                'remote' => $snapshot,
                'source' => $connection->sourceId(),
                'base' => $this->baseFrom($task),
                'done_origin' => null,
            ],
        ]);

        return $task;
    }

    /**
     * Weitere Arbeit desselben Laufs unter derselben Sperre (Redmine: Zeiteinträge).
     *
     * @param  TConnection  $connection
     * @param  EloquentCollection<int, TLink>  $links  bestätigte, laufende Zuordnungen
     */
    protected function afterImport(Organization $organization, Model $connection, EloquentCollection $links): void {}

    // ── Vorschau und ausdrückliches Verknüpfen ──────────────────────────

    /**
     * Vorschau einer Zuordnung ohne Schreibwirkung: was der erste Lauf mit den
     * offenen Aufgaben täte, dazu lokale Kandidaten mit gleichem Titel.
     *
     * @param  TLink  $link
     * @return array{rows: list<array{remote: RemoteTask, external_id: string, action: string, task: ?Task, candidate: ?Task}>, counts: array<string, int>, truncated: bool}
     */
    public function preview(Organization $organization, Model $link): array {
        $connection = $this->connection((int) $organization->id);
        if ($connection === null || ! $connection->isReady()) {
            throw new \RuntimeException('Verbindung nicht bereit.');
        }
        $mayWrite = app(DataOwnershipResolver::class)->mayWrite($organization, DataDomain::Tasks, $this->pluginId());

        /** @var array<string, RemoteTask> $remotes */
        $remotes = [];
        foreach ($this->fetch($connection, $link, null, false) as $raw) {
            $remote = $this->normalize($raw, $organization);
            $remotes[$this->externalId($connection, $remote->id)] = $remote;
        }

        $references = ExternalReference::query()
            ->forPlugin($organization, $this->pluginId(), $this->externalType())
            ->whereIn('external_id', array_keys($remotes))
            ->with('referenceable')
            ->get()
            ->keyBy('external_id');
        $candidates = $this->unlinkedTasks($link)->groupBy(fn (Task $t): string => mb_strtolower(trim((string) $t->title)));

        $rows = [];
        $counts = ['create' => 0, 'linked' => 0, 'mismatch' => 0, 'clarify' => 0, 'blocked' => 0, 'skip_done' => 0];
        foreach ($remotes as $externalId => $remote) {
            $reference = $references->get($externalId);
            $task = $reference?->referenceable instanceof Task ? $reference->referenceable : null;
            $action = match (true) {
                $reference !== null && $task === null => 'clarify',
                $task !== null => (int) $task->project_id === $link->targetProjectId() ? 'linked' : 'mismatch',
                ! $mayWrite => 'blocked',
                $remote->isDone() => 'skip_done',
                $remote->has(RemoteTask::UNKNOWN_STATUS), $remote->has(RemoteTask::EMPTY_TITLE) => 'clarify',
                default => 'create',
            };
            $counts[$action]++;
            $candidate = $action === 'create'
                ? $candidates->get(mb_strtolower(trim((string) ($remote->fields['title'] ?? ''))))?->first()
                : null;
            $rows[] = ['remote' => $remote, 'external_id' => $externalId, 'action' => $action, 'task' => $task, 'candidate' => $candidate];
        }

        return [
            'rows' => array_slice($rows, 0, self::PREVIEW_LIMIT),
            'counts' => $counts,
            'truncated' => count($rows) > self::PREVIEW_LIMIT,
        ];
    }

    /**
     * Bestehende Aufgabe ausdrücklich mit einer Quellaufgabe verknüpfen.
     * Abweichende Felder werden als Konflikt zur Prüfung vorgelegt
     * (leere Basis), leere lokale Felder übernommen.
     *
     * @param  TLink  $link
     * @return 'updated'|'unchanged'|'conflicts'
     */
    public function attach(Organization $organization, Model $link, string $remoteId, Task $task): string {
        return $this->locked((int) $organization->id, function () use ($organization, $link, $remoteId, $task): string {
            $connection = $this->connection((int) $organization->id);
            if ($connection === null || ! $connection->isReady()) {
                throw new TaskImportAttachException('not_ready');
            }
            if ((int) $task->organization_id !== (int) $organization->id || (int) $task->project_id !== $link->targetProjectId()) {
                throw new TaskImportAttachException('wrong_target');
            }
            if (ExternalReference::query()->forReferenceable($task)->exists()) {
                throw new TaskImportAttachException('task_linked');
            }
            if (! app(DataOwnershipResolver::class)->mayWrite($organization, DataDomain::Tasks, $this->pluginId())) {
                throw new TaskImportAttachException('ownership');
            }
            $raw = $this->fetchOne($connection, $link, $remoteId);
            if ($raw === null) {
                throw new TaskImportAttachException('not_visible');
            }
            $remote = $this->normalize($raw, $organization);
            $externalId = $this->externalId($connection, $remote->id);
            if (ExternalReference::query()->forPlugin($organization, $this->pluginId(), $this->externalType())->forExternalId($externalId)->exists()) {
                throw new TaskImportAttachException('remote_linked');
            }

            return DB::transaction(function () use ($organization, $connection, $link, $remote, $externalId, $task): string {
                $reference = ExternalReference::query()->create([
                    'organization_id' => $organization->id,
                    'plugin_id' => $this->pluginId(),
                    'external_type' => $this->externalType(),
                    'external_id' => $externalId,
                    'referenceable_type' => $task->getMorphClass(),
                    'referenceable_id' => $task->getKey(),
                    'synced_at' => now(),
                    'payload' => ['remote' => ['id' => $externalId] + $remote->snapshot, 'source' => $connection->sourceId(), 'base' => [], 'done_origin' => null],
                ]);

                return $this->applyRemote($link, $reference, $task, $remote, ['id' => $externalId] + $remote->snapshot);
            });
        });
    }

    /**
     * Aufgaben des Zielprojekts ohne jede Fremdreferenz (Kandidaten zum Verknüpfen).
     *
     * @param  TLink  $link
     * @return EloquentCollection<int, Task>
     */
    public function unlinkedTasks(Model $link): EloquentCollection {
        $task = new Task();

        return Task::query()
            ->where('organization_id', $link->organizationId())
            ->where('project_id', $link->targetProjectId())
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('external_references')
                ->where('referenceable_type', $task->getMorphClass())
                ->whereColumn('referenceable_id', $task->qualifyColumn('id')))
            ->orderBy('title')
            ->limit(500)
            ->get(['id', 'organization_id', 'project_id', 'title', 'status']);
    }

    // ── Konflikte ───────────────────────────────────────────────────────

    /**
     * Eine entschiedene Konfliktrunde wird zur Basis: „lokal behalten“ und
     * „verwerfen“ erkennen den damaligen Fremdstand an, „übernehmen“ hat ihn
     * lokal gesetzt. Die nächste Runde bekommt einen eigenen Fall.
     */
    private function acknowledgeResolvedConflict(ExternalReference $reference): void {
        $payload = (array) ($reference->payload ?? []);
        $round = (int) ($payload['conflict_round'] ?? 0);
        $item = IntegrationInboxItem::query()
            ->where('organization_id', $reference->organization_id)
            ->where('plugin_id', $this->pluginId())
            ->where('dedupe_key', $this->conflictKey((string) $reference->external_id, $round))
            ->where('status', '!=', IntegrationInboxStatus::Open)
            ->first();
        if ($item === null) {
            return;
        }

        $base = (array) ($payload['base'] ?? []);
        foreach ((array) ($item->diff_fields ?? []) as $field) {
            $base[(string) $field] = ($item->mapped_snapshot ?? [])[$field] ?? null;
        }
        $payload['base'] = $base;
        $payload['conflict_round'] = $round + 1;
        $reference->forceFill(['payload' => $payload])->save();
    }

    /**
     * Wie der Kern, aber je Konfliktrunde ein eigener Fall (siehe {@see acknowledgeResolvedConflict()}).
     *
     * @param  TLink  $link
     * @param  array<string, mixed>  $remote
     * @param  array<string, array{local: mixed, remote: mixed}>  $conflicts
     */
    protected function stageConflict(TaskSyncLink $link, ExternalReference $reference, Task $task, array $remote, array $conflicts): void {
        IntegrationInboxItem::query()->firstOrCreate([
            'organization_id' => $link->organizationId(),
            'plugin_id' => $this->pluginId(),
            'dedupe_key' => $this->conflictKey((string) $reference->external_id, (int) (((array) ($reference->payload ?? []))['conflict_round'] ?? 0)),
        ], [
            'source' => $this->pluginId(),
            'target_type' => $task->getMorphClass(),
            'external_type' => $this->externalType(),
            'external_id' => $reference->external_id,
            'case_type' => IntegrationInboxItem::CASE_CONFLICT,
            'status' => IntegrationInboxStatus::Open,
            'referenceable_type' => $task->getMorphClass(),
            'referenceable_id' => $task->getKey(),
            'remote_snapshot' => $remote,
            'local_snapshot' => $this->baseFrom($task),
            'mapped_snapshot' => collect($conflicts)->map(fn (array $c) => $c['remote'])->all(),
            'diff_fields' => array_keys($conflicts),
            'display_title' => (string) $task->title,
            'display_subtitle' => $this->displaySubtitle($link),
            'occurred_at' => now(),
        ]);
    }

    private function conflictKey(string $externalId, int $round): string {
        return $this->dedupePrefix() . '-conflict:' . $externalId . ($round > 0 ? ':' . $round : '');
    }

    // ── Hooks des Kerns ─────────────────────────────────────────────────

    protected function dedupePrefix(): string {
        return $this->externalType();
    }

    protected function doneOriginMarker(): string {
        return $this->pluginId();
    }

    /** @return list<string> */
    protected function syncedFields(): array {
        return ['title', 'description', 'status', 'due_date'];
    }

    /** @param TLink $link */
    protected function displaySubtitle(TaskSyncLink $link): string {
        return $link->sourceLabel();
    }

    /** @param TLink $link */
    protected function caseSubtitle(TaskSyncLink $link, string $case): string {
        return 'integration.task_import.case.' . $case;
    }

    /** @return array<string, mixed> */
    protected function remoteDeletedSnapshot(): array {
        return ['remote_missing' => true];
    }

    /** @param array<string, mixed> $remote */
    protected function remoteDisplayTitle(array $remote): string {
        $title = trim((string) ($remote['title'] ?? ''));
        $key = trim((string) ($remote['key'] ?? ''));

        return trim(($key !== '' ? $key . ' · ' : '') . ($title !== '' ? $title : '—'));
    }

    protected function withoutExportEcho(callable $callback): mixed {
        return $callback(); // reiner Import: kein Rückkanal, der ein Echo erzeugen könnte
    }

    /**
     * @param  TLink  $link
     * @param  array<string, mixed>  $remote
     * @param  array<string, mixed>  $newBase
     * @return array<string, mixed>
     */
    protected function updatedReferencePayload(TaskSyncLink $link, array $remote, array $newBase, mixed $doneOrigin): array {
        return [
            'remote' => $remote,
            'base' => $newBase,
            // done_origin gilt nur, solange die Aufgabe erledigt ist.
            'done_origin' => ($newBase['status'] ?? null) === TaskStatus::Done->value ? $doneOrigin : null,
        ];
    }

    /** @param TLink $link */
    protected function taskBelongsToLink(TaskSyncLink $link, Task $task): bool {
        return (int) $task->project_id === $link->targetProjectId();
    }

    // ── Intern ──────────────────────────────────────────────────────────

    /**
     * @param  array{created: int, updated: int, unchanged: int, conflicts: int, inbox: int, failed: int}  $counters
     * @param  'created'|'updated'|'unchanged'|'conflicts'|'inbox'|'failed'  $outcome
     * @return array{created: int, updated: int, unchanged: int, conflicts: int, inbox: int, failed: int}
     */
    private static function bump(array $counters, string $outcome): array {
        $counters[$outcome]++;

        return $counters;
    }

    /** @param TConnection $connection */
    public function externalId(Model $connection, string $remoteId): string {
        return $connection->sourceId() . ':' . $remoteId;
    }

    /** @return EloquentCollection<int, TLink> */
    protected function runnableLinks(int $organizationId): EloquentCollection {
        $model = $this->linkModel();

        return $model::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('status', TaskImportLinkStatus::Active)
            ->whereNotNull('import_confirmed_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array<int, TLink>  $links
     * @return TLink
     */
    private function linkFor(array $links, ?int $projectId): Model {
        foreach ($links as $link) {
            if ($link->targetProjectId() === $projectId) {
                return $link;
            }
        }

        return reset($links) ?: throw new \LogicException('Quellaufgabe ohne Zuordnung.');
    }

    /**
     * @template TResult
     *
     * @param  callable(): TResult  $callback
     * @return TResult
     */
    protected function locked(int $organizationId, callable $callback): mixed {
        $lock = Cache::lock('task-import:' . $this->pluginId() . ':' . $organizationId, self::LOCK_SECONDS);
        if (! $lock->get()) {
            throw TaskImportBusyException::for($this->pluginId(), $organizationId);
        }
        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }

    /**
     * Ungültige Anmeldung beendet den ganzen Lauf; 403 bleibt ein Fehler der
     * einen Zuordnung — Gitea, Vikunja, Kanboard, Plane und Co. melden damit
     * „kein Recht auf dieses Projekt“, nicht einen ungültigen Zugang.
     */
    protected function isAuthFailure(Throwable $e): bool {
        return $e instanceof UnauthorizedException
            || ($e instanceof PluginApiException && $e->status === 401);
    }

    protected function errorLabel(Throwable $e): string {
        return class_basename($e) . ($e instanceof PluginApiException && $e->status > 0 ? ' ' . $e->status : '');
    }

    /** @param array<string, mixed> $context */
    protected function recordError(Throwable $e, Organization $organization, array $context): void {
        try {
            app(PluginErrorRecorder::class)->record($this->pluginId(), 'runtime', $e, $context, (int) $organization->id);
        } catch (Throwable) {
            // Aufzeichnung darf den Lauf nicht reißen.
        }
    }
}
