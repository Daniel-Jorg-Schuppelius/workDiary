<?php
/*
 * Created on   : Sat Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GitlabIssueImporter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Gitlab\Services;

use App\Enums\Integration\DataDomain;
use App\Enums\Task\TaskStatus;
use App\Models\{ExternalReference, IntegrationInboxItem, Organization, PluginSetting, Project, Task};
use App\Plugins\Gitlab\Api\GitlabClient;
use App\Plugins\Gitlab\{GitlabConfig, GitlabPlugin};
use App\Services\Integration\DataOwnershipResolver;
use App\Services\SqidEncoder;
use Illuminate\Support\Carbon;

/**
 * Import von GitLab-Issues als WorkDiary-Aufgaben (Feature 060, MVP-129,
 * Bauturbo A6). GitLab bleibt führend; hier entstehen nur Aufgaben für
 * Zeiterfassung/Abrechnung. **Idempotent** über {@see ExternalReference}
 * (Plugin `gitlab`, Typ `issue`, Schlüssel `project_id#iid`): ein Replay legt
 * keine Dubletten an.
 *
 * - Identität: `iid` ist NUR projektbezogen eindeutig — der Schlüssel setzt
 *   sich deshalb aus `project_id` + `iid` zusammen; die globale `id` wird
 *   bewusst nie verwendet (Recherche 2026-07).
 * - Status: `opened` → offen, `closed` → erledigt. Ein wieder geöffnetes
 *   Issue öffnet die verknüpfte, erledigte Aufgabe erneut — gefahrlos,
 *   weil es keinen Status-Rückkanal gibt (kein Echo).
 * - Aufholpunkt: `updated_after` = größtes gesehenes `updated_at`
 *   (serverseitige Uhr), fortgeschrieben in plugin_settings.
 */
class GitlabIssueImporter {
    /** Settings-Schlüssel des Polling-Aufholpunkts (GitLab `updated_after`). */
    public const CHECKPOINT_KEY = 'updated_after_checkpoint';

    /**
     * @param  array{api_token: ?string, project_id: ?string, webhook_token: ?string, default_project: ?string, base_url: string, allow_private_network: bool, enabled: bool}|null  $config
     * @return array{created: int, updated: int, skipped: int, inbox: int}
     */
    public function import(Organization $organization, GitlabClient $client, ?array $config = null): array {
        $config ??= GitlabConfig::resolve((int) $organization->id);
        $projectId = (string) $config['project_id'];

        $counters = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'inbox' => 0];
        $row = $this->settingsRow($organization);
        $updatedAfter = $this->checkpoint($row);
        $maxUpdated = $updatedAfter;

        $wdProjectId = $this->resolveProject($organization, $config['default_project']);
        $ownership = app(DataOwnershipResolver::class);

        $perPage = 100;
        $maxPages = max(1, (int) config('plugins.gitlab.max_pages', 10));
        $page = 1;

        do {
            $issues = $client->issues($projectId, $updatedAfter, $page, $perPage);

            foreach ($issues as $issue) {
                $updatedAt = (string) ($issue['updated_at'] ?? '');
                if ($updatedAt !== '' && ($maxUpdated === null || strcmp($updatedAt, $maxUpdated) > 0)) {
                    $maxUpdated = $updatedAt;
                }

                // iid + project_id — NIE die globale id (Recherche 2026-07).
                $externalId = sprintf('%s#%d', $projectId, (int) ($issue['iid'] ?? 0));
                $target = $this->mapStatus((string) ($issue['state'] ?? 'opened'));

                $reference = ExternalReference::query()
                    ->where('organization_id', $organization->id)
                    ->where('plugin_id', GitlabPlugin::ID)
                    ->where('external_type', GitlabPlugin::EXT_TYPE_ISSUE)
                    ->where('external_id', $externalId)
                    ->first();

                if ($reference instanceof ExternalReference) {
                    $counters[$this->syncExisting($reference, $target, $issue) ? 'updated' : 'skipped']++;

                    continue;
                }

                // Datenführerschaft (Restpunkt 69): führt ein ANDERES Plugin den
                // Aufgabenbereich, landet das Issue als Inbox-Konflikt.
                if (! $ownership->mayWrite($organization, DataDomain::Tasks, GitlabPlugin::ID)) {
                    $counters['inbox'] += $this->ownershipConflict($organization, $ownership, $externalId, $issue);

                    continue;
                }

                $task = Task::query()->create([
                    'organization_id' => $organization->id,
                    'project_id' => $wdProjectId,
                    'is_global' => $wdProjectId === null,
                    'title' => $this->taskTitle($issue),
                    'status' => $target->value,
                    'billable' => true,
                ]);

                ExternalReference::query()->create([
                    'organization_id' => $organization->id,
                    'plugin_id' => GitlabPlugin::ID,
                    'external_type' => GitlabPlugin::EXT_TYPE_ISSUE,
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

    /** `opened` → offen; `closed` → erledigt. */
    private function mapStatus(string $state): TaskStatus {
        return $state === 'closed' ? TaskStatus::Done : TaskStatus::Open;
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

        $task->forceFill(['status' => $target->value])->save();
        $reference->forceFill(['payload' => $issue, 'synced_at' => Carbon::now()])->save();

        return true;
    }

    /** @param array<string, mixed> $issue */
    private function ownershipConflict(Organization $organization, DataOwnershipResolver $ownership, string $externalId, array $issue): int {
        $item = IntegrationInboxItem::query()->firstOrCreate(
            [
                'organization_id' => $organization->id,
                'plugin_id' => GitlabPlugin::ID,
                'dedupe_key' => 'ownership-conflict:issue:' . $externalId,
            ],
            [
                'source' => GitlabPlugin::ID,
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

    /** @param array<string, mixed> $issue */
    private function taskTitle(array $issue): string {
        $iid = (int) ($issue['iid'] ?? 0);
        $title = trim((string) ($issue['title'] ?? ''));

        return '#' . $iid . ' ' . ($title !== '' ? $title : ('Issue ' . $iid));
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
            ->where('plugin_id', GitlabPlugin::ID)
            ->first();
    }

    private function checkpoint(?PluginSetting $row): ?string {
        $value = $row?->get(self::CHECKPOINT_KEY);

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

        $row->set(self::CHECKPOINT_KEY, $maxUpdated);
        $row->save();
    }
}
