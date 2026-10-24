<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RedmineTimeImportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Redmine\Services;

use App\Enums\Integration\IntegrationInboxStatus;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\Integration\{ExternalReference, IntegrationInboxItem};
use App\Models\Platform\{Organization, User};
use App\Models\Project\Task;
use App\Models\Time\TimeEntry;
use App\Plugins\Redmine\Api\{RedmineClient, RedmineClientFactory};
use App\Plugins\Redmine\Models\{RedmineConnection, RedmineProjectLink};
use App\Plugins\Redmine\{RedmineConfig, RedminePlugin};
use App\Plugins\Support\{PersistsTimeImportInbox, ReconcilesRemoteDeletions, RemoteSyncWindow, RemoteTimeFingerprint, TimeWritebackObserver};
use App\Support\{MorphMap, Tz};
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\DateHelper;
use Illuminate\Support\Collection;

/**
 * Redmine-Zeiteinträge (MVP-1323) eingeschalteter Zuordnungen im rollierenden
 * Fenster: Redmine kennt nur Tag und Stunden — gebucht wird wie bei
 * OpenProject als Datum plus Minuten ohne Uhrzeit, der Fingerabdruck über die
 * Dauer. Zielprojekt ist das der Zuordnung, die Aufgabe die des verknüpften
 * Issues. Der Benutzer kommt nur aus einer ausdrücklichen Zuordnung; ohne sie
 * wartet der Eintrag als Klärfall, statt still beim Hauptbenutzer zu landen.
 * Was im vollständig gelesenen Fenster fehlt, ist in Redmine gelöscht
 * (abgerechnete Zeiten werden zum Konflikt).
 */
class RedmineTimeImportService {
    use PersistsTimeImportInbox;
    use ReconcilesRemoteDeletions;

    public const EXT_TYPE_ENTRY = 'entry';

    public const EXT_TYPE_USER = 'user';

    /** Gruppen-Präfix der Fälle „Redmine-Benutzer zuordnen“. */
    public const USER_GROUP_PREFIX = 'user:';

    public function __construct(private readonly RedmineClientFactory $clients) {}

    protected function pluginId(): string {
        return RedminePlugin::ID;
    }

    /** @return array{created: int, updated: int, skipped: int, unresolved_users: int, conflicts: int, removed: int, failed: int} */
    public static function emptyCounters(): array {
        return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'unresolved_users' => 0, 'conflicts' => 0, 'removed' => 0, 'failed' => 0];
    }

    /**
     * @param  iterable<RedmineProjectLink>  $links
     * @return array{created: int, updated: int, skipped: int, unresolved_users: int, conflicts: int, removed: int, failed: int}
     */
    public function import(Organization $organization, RedmineConnection $connection, iterable $links): array {
        $config = RedmineConfig::resolve((int) $organization->id);
        $to = CarbonImmutable::now(Tz::ofOrganization($organization))->startOfDay();
        $from = $to->subDays($config['time_window_days']);
        $totals = self::emptyCounters();
        $client = null;

        foreach ($links as $link) {
            if (! $link->is_time_import_enabled || ! $link->isRunnable()) {
                continue;
            }
            if ($client === null) {
                // Ein ungültiger Schlüssel liest anonym weiter; die Löschungserkennung liefe dann auf Teildaten.
                $client = $this->clients->for((int) $organization->id);
                $client->currentUser();
            }
            $counters = TimeWritebackObserver::suppressed(fn (): array => $this->importLink($client, $organization, $connection, $link, $from, $to, $config['default_billable']));
            $link->forceFill(['last_time_import_at' => CarbonImmutable::now(), 'last_time_counters' => $counters])->save();
            foreach ($totals as $key => $value) {
                $totals[$key] = $value + $counters[$key];
            }
        }

        return $totals;
    }

    /** Fremd-ID eines Redmine-Benutzers dieser Instanz (Schlüssel der Benutzerzuordnung). */
    public static function userKey(RedmineConnection $connection, string $redmineUserId): string {
        return $connection->sourceId() . ':' . $redmineUserId;
    }

    /**
     * Redmine-Benutzer mit wartenden Zeiteinträgen (für die Zuordnung auf der Plugin-Seite).
     *
     * @return Collection<int, array{key: string, name: string, count: int}>
     */
    public function pendingUsers(Organization $organization): Collection {
        return $this->openInboxItems($organization)
            ->filter(fn (IntegrationInboxItem $item): bool => str_starts_with((string) $item->group_key, self::USER_GROUP_PREFIX))
            ->groupBy('group_key')
            ->map(fn (Collection $items, string $group): array => [
                'key' => substr($group, strlen(self::USER_GROUP_PREFIX)),
                'name' => (string) data_get($items->first()?->remote_snapshot, 'user_name', '—'),
                'count' => $items->count(),
            ])
            ->values();
    }

    /**
     * @return array{created: int, updated: int, skipped: int, unresolved_users: int, conflicts: int, removed: int, failed: int}
     */
    private function importLink(RedmineClient $client, Organization $organization, RedmineConnection $connection, RedmineProjectLink $link, CarbonImmutable $from, CarbonImmutable $to, bool $defaultBillable): array {
        $counters = self::emptyCounters();
        $prefix = 'redmine:' . $connection->sourceId() . ':' . $link->sourceProjectId() . ':';
        $seen = [];
        $rows = $client->timeEntries($link->sourceProjectId(), $from->toDateString(), $to->toDateString());

        foreach ($rows as $row) {
            $entry = is_array($row) ? $this->entry($row, $link) : null;
            if ($entry === null) {
                $counters['failed']++;

                continue;
            }
            $key = $prefix . $entry['id'];
            $seen[] = $key;
            $outcome = $this->alreadyImported($organization, $key)
                ? $this->syncKnown($organization, $key, $entry)
                : $this->book($organization, $connection, $link, $key, $entry, $defaultBillable);
            $counters[$outcome]++;
        }

        // Ein unlesbarer Eintrag macht das Fenster unvollständig — dann nichts als gelöscht werten.
        if ($counters['failed'] === 0) {
            $counters['removed'] = $this->reconcileRemoteDeletions($organization, $seen, new RemoteSyncWindow($from, $to), self::EXT_TYPE_ENTRY, $prefix);
        }

        return $counters;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{id: string, spent_on: CarbonImmutable, minutes: int, user_id: string, user_name: string, issue_id: ?string, description: string}|null
     */
    private function entry(array $row, RedmineProjectLink $link): ?array {
        $id = is_scalar($row['id'] ?? null) ? (string) $row['id'] : '';
        $spentOn = $row['spent_on'] ?? null;
        $hours = $row['hours'] ?? null;
        $userId = is_scalar(data_get($row, 'user.id')) ? (string) data_get($row, 'user.id') : '';
        if (! ctype_digit($id) || ! is_string($spentOn) || ! DateHelper::isValidDate($spentOn, ['Y-m-d']) || ! is_numeric($hours) || ! ctype_digit($userId)
            || (string) data_get($row, 'project.id') !== $link->sourceProjectId()) {
            return null;
        }
        $comment = trim((string) ($row['comments'] ?? ''));
        $activity = trim((string) data_get($row, 'activity.name', ''));

        return [
            'id' => $id,
            'spent_on' => CarbonImmutable::parse($spentOn),
            'minutes' => max(0, (int) round((float) $hours * 60)),
            'user_id' => $userId,
            'user_name' => trim((string) data_get($row, 'user.name', '')) ?: '#' . $userId,
            'issue_id' => is_scalar(data_get($row, 'issue.id')) ? (string) data_get($row, 'issue.id') : null,
            'description' => $comment !== '' ? $comment : ($activity !== '' ? $activity : (string) __('redmine::redmine.time.fallback')),
        ];
    }

    /**
     * @param  array{id: string, spent_on: CarbonImmutable, minutes: int, user_id: string, user_name: string, issue_id: ?string, description: string}  $entry
     * @return 'created'|'unresolved_users'
     */
    private function book(Organization $organization, RedmineConnection $connection, RedmineProjectLink $link, string $key, array $entry, bool $defaultBillable): string {
        $user = ExternalReference::query()
            ->forPlugin($organization, $this->pluginId(), self::EXT_TYPE_USER)
            ->forExternalId(self::userKey($connection, $entry['user_id']))
            ->first()?->referenceable;
        if (! $user instanceof User || (int) $user->organization_id !== (int) $organization->id) {
            $this->recordPendingItem($organization, $key, [
                'source' => 'api',
                'group_key' => self::USER_GROUP_PREFIX . self::userKey($connection, $entry['user_id']),
                'remote_snapshot' => [
                    'reason' => 'redmine_user_unmapped',
                    'user_id' => $entry['user_id'],
                    'user_name' => $entry['user_name'],
                    'spent_on' => $entry['spent_on']->toDateString(),
                    'minutes' => $entry['minutes'],
                    'project' => $link->sourceLabel(),
                    'description' => $entry['description'],
                ],
                'display_title' => $entry['user_name'],
                'display_subtitle' => 'integration.task_import.case.time_user_unmapped',
                'occurred_at' => $entry['spent_on'],
            ]);

            return 'unresolved_users';
        }

        $attributes = [
            'organization_id' => $organization->id,
            'project_id' => $link->targetProjectId(),
            'task_id' => $this->taskFor($organization, $connection, $link, $entry['issue_id']),
            'user_id' => $user->id,
            'date' => $entry['spent_on']->toDateString(),
            'minutes' => $entry['minutes'],
            'kind' => TimeEntryKind::Work,
            'description' => $entry['description'],
        ];
        if (! $defaultBillable) {
            $attributes['billable'] = false;
        }
        $timeEntry = TimeEntry::query()->create($attributes);

        ExternalReference::query()->create([
            'organization_id' => $organization->id,
            'plugin_id' => $this->pluginId(),
            'external_type' => self::EXT_TYPE_ENTRY,
            'referenceable_type' => $timeEntry->getMorphClass(),
            'referenceable_id' => $timeEntry->getKey(),
            'external_id' => $key,
            'payload' => [
                'project' => $link->sourceProjectId(),
                'issue' => $entry['issue_id'],
                'user' => $entry['user_id'],
                'fingerprint' => RemoteTimeFingerprint::fromDuration($entry['spent_on'], $entry['minutes']),
            ],
            'synced_at' => now(),
        ]);
        $this->closePendingItems($organization, $key, $timeEntry);

        return 'created';
    }

    /**
     * @param  array{id: string, spent_on: CarbonImmutable, minutes: int, user_id: string, user_name: string, issue_id: ?string, description: string}  $entry
     * @return 'updated'|'skipped'|'conflicts'
     */
    private function syncKnown(Organization $organization, string $key, array $entry): string {
        $reference = ExternalReference::query()
            ->forPlugin($organization, $this->pluginId(), self::EXT_TYPE_ENTRY)
            ->forExternalId($key)
            ->first();
        $current = RemoteTimeFingerprint::fromDuration($entry['spent_on'], $entry['minutes']);
        $known = is_array($reference?->payload) ? (string) ($reference->payload['fingerprint'] ?? '') : '';
        $timeEntry = $reference?->referenceable;
        if ($reference === null || $known === $current || ! $timeEntry instanceof TimeEntry) {
            return 'skipped';
        }

        if ($timeEntry->exported) {
            IntegrationInboxItem::query()->withoutGlobalScopes()->updateOrCreate(
                ['organization_id' => $organization->id, 'plugin_id' => $this->pluginId(), 'dedupe_key' => $this->pluginId() . '-remote-changed:' . $key],
                [
                    'source' => $this->pluginId(),
                    'target_type' => MorphMap::alias(TimeEntry::class),
                    'external_type' => self::EXT_TYPE_ENTRY,
                    'external_id' => $key,
                    'case_type' => IntegrationInboxItem::CASE_CONFLICT,
                    'referenceable_type' => $timeEntry->getMorphClass(),
                    'referenceable_id' => $timeEntry->getKey(),
                    'status' => IntegrationInboxStatus::Open,
                    'remote_snapshot' => [
                        'reason' => 'remote_changed_after_export',
                        'resolution' => IntegrationInboxItem::RESOLUTION_ACKNOWLEDGE_ONLY,
                        'remote' => ['spent_on' => $entry['spent_on']->toDateString(), 'minutes' => $entry['minutes']],
                        'local' => ['date' => $timeEntry->date?->toDateString(), 'minutes' => $timeEntry->minutes],
                    ],
                ],
            );

            return 'conflicts';
        }

        $timeEntry->forceFill(['date' => $entry['spent_on']->toDateString(), 'minutes' => $entry['minutes']])->save();
        $reference->forceFill(['payload' => ['fingerprint' => $current] + (array) $reference->payload, 'synced_at' => now()])->save();

        return 'updated';
    }

    /** Aufgabe des verknüpften Issues, nur im Zielprojekt der Zuordnung. */
    private function taskFor(Organization $organization, RedmineConnection $connection, RedmineProjectLink $link, ?string $issueId): ?int {
        if ($issueId === null) {
            return null;
        }
        $task = ExternalReference::query()
            ->forPlugin($organization, $this->pluginId(), RedminePlugin::EXT_TYPE_ISSUE)
            ->forExternalId($connection->sourceId() . ':' . $issueId)
            ->first()?->referenceable;

        return $task instanceof Task && (int) $task->project_id === $link->targetProjectId() ? (int) $task->id : null;
    }
}
