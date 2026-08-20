<?php
/*
 * Created on   : Fri Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AbstractGitIssueImporter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\GitIssueImport;

use App\Enums\Integration\DataDomain;
use App\Enums\Task\TaskStatus;
use App\Models\{ExternalReference, IntegrationInboxItem, Organization, PluginSetting, Project, Task};
use App\Services\Integration\DataOwnershipResolver;
use App\Services\SqidEncoder;
use Illuminate\Support\Carbon;

/**
 * Gemeinsames Skeleton des Git-Issue-Imports (Feature 060, Konsolidierung
 * C8): Checkpoint in plugin_settings, ExternalReference-Upsert, Status-
 * Mapping, Ownership-Konflikt → Inbox. Das Ticketsystem bleibt führend;
 * hier entstehen nur Aufgaben, **idempotent** über ExternalReference —
 * Referenz-Schlüssel/-Typen und Checkpoint-Keys liefern die Subklassen
 * unverändert (Bestandsdaten!). Provider-spezifisch bleiben Client und
 * Payload-Mapper (Feldnamen, PR-Filter).
 */
abstract class AbstractGitIssueImporter {
    abstract protected function pluginId(): string;

    /** ExternalReference-`external_type` (Bestandsdaten — nie ändern). */
    abstract protected function externalType(): string;

    /** Settings-Schlüssel des Polling-Aufholpunkts (Bestandsdaten — nie ändern). */
    abstract protected function checkpointKey(): string;

    /** Config-Key der Seiten-Obergrenze (z. B. `plugins.github.max_pages`). */
    abstract protected function maxPagesConfigKey(): string;

    /** Idempotenz-Schlüssel des Issues (Format bleibt je Provider stabil). */
    /** @param array<string, mixed> $issue */
    abstract protected function externalId(array $issue): string;

    /** @param array<string, mixed> $issue */
    abstract protected function taskTitle(array $issue): string;

    /** Feed-Eintrag überspringen (z. B. GitHub liefert auch Pull Requests)? */
    /** @param array<string, mixed> $issue */
    protected function shouldSkip(array $issue): bool {
        return false;
    }

    /** `closed` → erledigt (beide Provider); alles andere → offen. */
    /** @param array<string, mixed> $issue */
    protected function mapStatus(array $issue): TaskStatus {
        return (string) ($issue['state'] ?? '') === 'closed' ? TaskStatus::Done : TaskStatus::Open;
    }

    /**
     * Template-Methode: Feed seitenweise holen, Referenzen upserten,
     * Aufholpunkt fortschreiben. Der Checkpoint bleibt während des Laufs
     * konstant (Pagination läuft mit demselben Wert weiter).
     *
     * @param  callable(?string $checkpoint, int $page, int $perPage): list<array<string, mixed>>  $fetchPage
     * @return array{created: int, updated: int, skipped: int, inbox: int}
     */
    protected function runImport(Organization $organization, ?string $defaultProjectSqid, callable $fetchPage): array {
        $counters = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'inbox' => 0];
        $row = $this->settingsRow($organization);
        $checkpoint = $this->checkpoint($row);
        $maxUpdated = $checkpoint;

        $projectId = $this->resolveProject($organization, $defaultProjectSqid);
        $ownership = app(DataOwnershipResolver::class);

        $perPage = 100;
        $maxPages = max(1, (int) config($this->maxPagesConfigKey(), 10));
        $page = 1;

        do {
            $issues = $fetchPage($checkpoint, $page, $perPage);

            foreach ($issues as $issue) {
                // Aufholpunkt über ALLE Feed-Einträge (auch gefilterte)
                // fortschreiben, damit sie nicht endlos erneut geholt werden.
                $updatedAt = (string) ($issue['updated_at'] ?? '');
                if ($updatedAt !== '' && ($maxUpdated === null || strcmp($updatedAt, $maxUpdated) > 0)) {
                    $maxUpdated = $updatedAt;
                }

                if ($this->shouldSkip($issue)) {
                    continue;
                }

                $externalId = $this->externalId($issue);
                $target = $this->mapStatus($issue);

                $reference = ExternalReference::query()
                    ->forPlugin($organization, $this->pluginId(), $this->externalType())
                    ->forExternalId($externalId)
                    ->first();

                if ($reference instanceof ExternalReference) {
                    $counters[$this->syncExisting($reference, $target, $issue) ? 'updated' : 'skipped']++;

                    continue;
                }

                // Datenführerschaft (Restpunkt 69): führt ein ANDERES Plugin den
                // Aufgabenbereich, landet das Issue als Inbox-Konflikt.
                if (! $ownership->mayWrite($organization, DataDomain::Tasks, $this->pluginId())) {
                    $counters['inbox'] += $this->ownershipConflict($organization, $ownership, $externalId, $issue);

                    continue;
                }

                $task = Task::query()->create([
                    'organization_id' => $organization->id,
                    'project_id' => $projectId,
                    'is_global' => $projectId === null,
                    'title' => $this->taskTitle($issue),
                    'status' => $target->value,
                    'billable' => true,
                ]);

                ExternalReference::query()->create([
                    'organization_id' => $organization->id,
                    'plugin_id' => $this->pluginId(),
                    'external_type' => $this->externalType(),
                    'referenceable_type' => $task->getMorphClass(),
                    'referenceable_id' => $task->id,
                    'external_id' => $externalId,
                    'payload' => $issue,
                    'synced_at' => Carbon::now(),
                ]);
                $counters['created']++;
            }

            $page++;
        } while (count($issues) === $perPage && $page <= $maxPages);

        $this->persistCheckpoint($row, $maxUpdated);

        return $counters;
    }

    /**
     * Statusabgleich einer bereits verknüpften Aufgabe: das Ticketsystem bleibt
     * führend — geschlossen ⇒ erledigt, wieder geöffnet ⇒ erledigte Aufgabe
     * wieder öffnen. `in_progress` bleibt bei offenem Issue unangetastet.
     *
     * @param  array<string, mixed>  $issue
     */
    private function syncExisting(ExternalReference $reference, TaskStatus $target, array $issue): bool {
        $task = $reference->referenceable;
        if (! $task instanceof Task) {
            return false;
        }

        $shouldUpdate = ($target === TaskStatus::Done && $task->status !== TaskStatus::Done)
            || ($target === TaskStatus::Open && $task->status === TaskStatus::Done);
        if (! $shouldUpdate) {
            return false;
        }

        // Kein Export-Echo (Welle 1.4): Remote-Statuswechsel dürfen nicht als
        // Rückrichtungs-Outbox zurück zum Quellsystem laufen.
        GitIssueWritebackObserver::suppressed(fn () => $task->forceFill(['status' => $target->value])->save());
        $reference->forceFill(['payload' => $issue, 'synced_at' => Carbon::now()])->save();

        return true;
    }

    /** @param array<string, mixed> $issue */
    private function ownershipConflict(Organization $organization, DataOwnershipResolver $ownership, string $externalId, array $issue): int {
        $item = IntegrationInboxItem::query()->firstOrCreate(
            [
                'organization_id' => $organization->id,
                'plugin_id' => $this->pluginId(),
                'dedupe_key' => 'ownership-conflict:issue:' . $externalId,
            ],
            [
                'source' => $this->pluginId(),
                'target_type' => (new Task)->getMorphClass(),
                'external_type' => 'issue_ownership_conflict',
                'external_id' => $externalId,
                'case_type' => IntegrationInboxItem::CASE_UNMATCHED,
                'status' => IntegrationInboxItem::STATUS_OPEN,
                'remote_snapshot' => [
                    'issue' => $externalId,
                    'title' => (string) ($issue['title'] ?? ''),
                    'reason' => 'Aufgabenbereich wird von "' . $ownership->ownerFor($organization, DataDomain::Tasks) . '" geführt.',
                ],
            ],
        );

        return $item->wasRecentlyCreated ? 1 : 0;
    }

    /** Sqid → Projekt-ID der eigenen Organisation (Mandantengrenze), sonst null. */
    private function resolveProject(Organization $organization, ?string $sqid): ?int {
        if ($sqid === null || $sqid === '') {
            return null;
        }
        $decoded = app(SqidEncoder::class)->decode(Project::class, $sqid);
        if ($decoded === null) {
            return null;
        }

        return Project::query()->whereKey($decoded)->where('organization_id', $organization->id)->exists()
            ? $decoded
            : null;
    }

    private function settingsRow(Organization $organization): ?PluginSetting {
        return PluginSetting::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', $this->pluginId())
            ->first();
    }

    private function checkpoint(?PluginSetting $row): ?string {
        $value = $row?->get($this->checkpointKey());

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Aufholpunkt nur fortschreiben, wenn eine Settings-Zeile existiert — eine
     * reine ENV-Konfiguration bekommt keine (unvollständige) Zeile untergeschoben.
     */
    private function persistCheckpoint(?PluginSetting $row, ?string $maxUpdated): void {
        if ($row === null || $maxUpdated === null || $maxUpdated === $this->checkpoint($row)) {
            return;
        }

        $row->set($this->checkpointKey(), $maxUpdated);
        $row->save();
    }
}
