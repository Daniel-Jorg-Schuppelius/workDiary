<?php
/*
 * Created on   : Sat Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GithubIssueImporter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Github\Services;

use App\Enums\Integration\DataDomain;
use App\Enums\Task\TaskStatus;
use App\Models\{ExternalReference, IntegrationInboxItem, Organization, PluginSetting, Project, Task};
use App\Plugins\Github\Api\GithubClient;
use App\Plugins\Github\{GithubConfig, GithubPlugin};
use App\Services\Integration\DataOwnershipResolver;
use App\Services\SqidEncoder;
use Illuminate\Support\Carbon;

/**
 * Import von GitHub-Issues als WorkDiary-Aufgaben (Feature 060, MVP-129,
 * Bauturbo A6). GitHub bleibt führend; hier entstehen nur Aufgaben für
 * Zeiterfassung/Abrechnung. **Idempotent** über {@see ExternalReference}
 * (Plugin `github`, Typ `issue`, Schlüssel `owner/repo#number`): ein Replay
 * legt keine Dubletten an.
 *
 * - Die Issues-API liefert AUCH Pull Requests — Einträge mit
 *   `pull_request`-Schlüssel werden gefiltert (Recherche 2026-07).
 * - Status: `open` → offen, `closed` → erledigt (state_reason
 *   completed/not_planned landet im Payload; der Aufgaben-Vertrag kennt
 *   keinen Verwerfen-Status). Ein wieder geöffnetes Issue öffnet die
 *   verknüpfte, erledigte Aufgabe erneut — anders als beim create-only-
 *   Zammad-Muster gefahrlos, weil es keinen Status-Rückkanal gibt (kein Echo).
 * - Aufholpunkt: `since` = größtes gesehenes `updated_at` (serverseitige
 *   Uhr, kein Client-Drift), fortgeschrieben in plugin_settings.
 */
class GithubIssueImporter {
    /** Settings-Schlüssel des Polling-Aufholpunkts (GitHub `since`). */
    public const CHECKPOINT_KEY = 'since_checkpoint';

    /**
     * @param  array{api_token: ?string, repo_owner: ?string, repo_name: ?string, webhook_secret: ?string, default_project: ?string, base_url: string, enabled: bool}|null  $config
     * @return array{created: int, updated: int, skipped: int, inbox: int}
     */
    public function import(Organization $organization, GithubClient $client, ?array $config = null): array {
        $config ??= GithubConfig::resolve((int) $organization->id);
        $owner = (string) $config['repo_owner'];
        $repo = (string) $config['repo_name'];

        $counters = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'inbox' => 0];
        $row = $this->settingsRow($organization);
        $since = $this->checkpoint($row);
        $maxUpdated = $since;

        $projectId = $this->resolveProject($organization, $config['default_project']);
        $ownership = app(DataOwnershipResolver::class);

        $perPage = 100;
        $maxPages = max(1, (int) config('plugins.github.max_pages', 10));
        $page = 1;

        do {
            $issues = $client->issues($owner, $repo, $since, $page, $perPage);

            foreach ($issues as $issue) {
                // Aufholpunkt über ALLE Feed-Einträge (auch PRs) fortschreiben,
                // damit gefilterte Einträge nicht endlos erneut geholt werden.
                $updatedAt = (string) ($issue['updated_at'] ?? '');
                if ($updatedAt !== '' && ($maxUpdated === null || strcmp($updatedAt, $maxUpdated) > 0)) {
                    $maxUpdated = $updatedAt;
                }

                // Recherche 2026-07: die Issues-API liefert auch Pull Requests.
                if (isset($issue['pull_request'])) {
                    continue;
                }

                $externalId = sprintf('%s/%s#%d', $owner, $repo, (int) ($issue['number'] ?? 0));
                $target = $this->mapStatus((string) ($issue['state'] ?? 'open'));

                $reference = ExternalReference::query()
                    ->where('organization_id', $organization->id)
                    ->where('plugin_id', GithubPlugin::ID)
                    ->where('external_type', GithubPlugin::EXT_TYPE_ISSUE)
                    ->where('external_id', $externalId)
                    ->first();

                if ($reference instanceof ExternalReference) {
                    $counters[$this->syncExisting($reference, $target, $issue) ? 'updated' : 'skipped']++;

                    continue;
                }

                // Datenführerschaft (Restpunkt 69): führt ein ANDERES Plugin den
                // Aufgabenbereich, landet das Issue als Inbox-Konflikt.
                if (! $ownership->mayWrite($organization, DataDomain::Tasks, GithubPlugin::ID)) {
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
                    'plugin_id' => GithubPlugin::ID,
                    'external_type' => GithubPlugin::EXT_TYPE_ISSUE,
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

    /** `open` → offen; `closed` (completed UND not_planned) → erledigt. */
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
                'plugin_id' => GithubPlugin::ID,
                'dedupe_key' => 'ownership-conflict:issue:' . $externalId,
            ],
            [
                'source' => GithubPlugin::ID,
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
        $number = (int) ($issue['number'] ?? 0);
        $title = trim((string) ($issue['title'] ?? ''));

        return '#' . $number . ' ' . ($title !== '' ? $title : ('Issue ' . $number));
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
            ->where('plugin_id', GithubPlugin::ID)
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
