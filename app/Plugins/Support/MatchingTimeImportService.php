<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MatchingTimeImportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support;

use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\{Customer, ExternalReference, ExternalReferenceAlias, IntegrationInboxItem, Organization, Project, TimeEntry, User};
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Gemeinsame Import-Pipeline der Zeit-Migrations-Plugins (Kimai, Clockify, …)
 * über {@see ImportedTimeEntry}-DTOs: Fremd-Kunde → Kunde, Fremd-Projekt →
 * Projekt ausschließlich über bestehende {@see ExternalReference}/
 * Namensgleichheit matchen (kein Auto-Anlegen). Treffer → TimeEntry
 * (idempotent über die `entry`-Reference); kein Treffer → universelle
 * Zuordnungs-Inbox (gruppiert nach Kunde|Projekt|Tätigkeit). Die Inbox bucht
 * Gruppen gegen Kunde + Projekt und merkt die Referenzen, sodass Folgeimporte
 * automatisch matchen. Liefert die Quelle numerische Fremd-IDs mit
 * (API-Import), werden sie als `client_id`-/`project_id`-References gemerkt —
 * umbenennungsfestes Matching und ggf. Export-Mapping des Plugins.
 */
abstract class MatchingTimeImportService {
    public const EXT_TYPE_CLIENT = 'client';

    public const EXT_TYPE_PROJECT = 'project';

    public const EXT_TYPE_ENTRY = 'entry';

    /** Numerische Fremd-IDs (nur API-Quelle) — Grundlage des Export-Mappings. */
    public const EXT_TYPE_CLIENT_ID = 'client_id';

    public const EXT_TYPE_PROJECT_ID = 'project_id';

    public const SUGGEST_THRESHOLD = 0.82;

    /** Plugin-Id, unter der References/Inbox-Items abgelegt werden. */
    abstract protected function pluginId(): string;

    /**
     * Effektive Plugin-Konfiguration der Organisation (für Inbox-Buchungen).
     *
     * @return array<string, mixed>
     */
    abstract protected function resolveConfig(int $organizationId): array;

    /** Beschreibungs-Fallback, wenn Projekt/Tätigkeit/Beschreibung leer sind. */
    abstract protected function fallbackDescription(): string;

    /**
     * @param  array<int, ImportedTimeEntry>  $entries
     * @param  array<string, mixed>  $config
     * @return array{created: int, skipped: int, unmatched: int}
     */
    protected function ingest(Organization $organization, array $entries, array $config): array {
        $created = 0;
        $skipped = 0;
        $unmatched = 0;

        $userId = $this->resolveBookingUserId($organization, isset($config['default_user_id']) && is_numeric($config['default_user_id']) ? (int) $config['default_user_id'] : null);
        if ($userId === null) {
            return ['created' => 0, 'skipped' => 0, 'unmatched' => 0];
        }

        foreach ($entries as $entry) {
            if ($this->alreadyImported($organization, $entry->entryKey)) {
                $skipped++;

                continue;
            }

            $project = $this->matchProject($organization, $entry);
            if ($project === null) {
                $this->recordPending($organization, $entry);
                $unmatched++;

                continue;
            }

            $this->createTimeEntry($organization, $project, $entry, $userId, (bool) ($config['default_billable'] ?? true));
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped, 'unmatched' => $unmatched];
    }

    public function matchCustomer(Organization $organization, ?string $clientName): ?Customer {
        $clientName = $clientName !== null ? trim($clientName) : '';
        if ($clientName === '') {
            return null;
        }

        $byName = $this->resolveByReference($organization, self::EXT_TYPE_CLIENT, $clientName);
        if ($byName instanceof Customer) {
            return $byName;
        }

        return Customer::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where(function ($q) use ($clientName): void {
                $q->whereRaw('LOWER(name) = ?', [mb_strtolower($clientName)])
                    ->orWhereRaw('LOWER(company) = ?', [mb_strtolower($clientName)]);
            })
            ->first();
    }

    public function matchProject(Organization $organization, ImportedTimeEntry $entry): ?Project {
        // API-Quelle: die numerische Fremd-Projekt-ID ist der stabilste Schlüssel
        // (übersteht Umbenennungen im Quellsystem).
        if ($entry->projectId !== null) {
            $byId = $this->resolveByReference($organization, self::EXT_TYPE_PROJECT_ID, (string) $entry->projectId);
            if ($byId instanceof Project) {
                return $byId;
            }
        }

        $projectName = $entry->projectName !== null ? trim($entry->projectName) : '';
        if ($projectName === '') {
            return null;
        }

        $byName = $this->resolveByReference($organization, self::EXT_TYPE_PROJECT, $this->projectKey($entry->clientName, $projectName));
        if ($byName instanceof Project) {
            return $byName;
        }

        $customer = $this->matchCustomer($organization, $entry->clientName);

        $query = Project::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($projectName)]);

        if ($customer !== null) {
            $query->where('customer_id', $customer->id);
        }

        return $query->first();
    }

    protected function resolveByReference(Organization $organization, string $externalType, string $externalId): ?Model {
        if ($externalId === '') {
            return null;
        }

        $ref = ExternalReference::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', $this->pluginId())
            ->where('external_type', $externalType)
            ->where('external_id', $externalId)
            ->first();

        if ($ref?->referenceable instanceof Model) {
            return $ref->referenceable;
        }

        return ExternalReferenceAlias::resolveModel($organization->id, $this->pluginId(), $externalType, $externalId);
    }

    public function suggestCustomer(Organization $organization, ?string $clientName): ?Customer {
        $needle = $this->normalize($clientName);
        if ($needle === '') {
            return null;
        }

        $best = null;
        $bestScore = 0.0;
        foreach (Customer::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->whereNull('archived_at')->get() as $customer) {
            $score = max($this->similarity($needle, $this->normalize($customer->name)), $this->similarity($needle, $this->normalize($customer->company)));
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $customer;
            }
        }

        return $bestScore >= self::SUGGEST_THRESHOLD ? $best : null;
    }

    public function suggestProject(Organization $organization, ?Customer $customer, ?string $projectName): ?Project {
        $needle = $this->normalize($projectName);
        if ($needle === '') {
            return null;
        }

        $query = Project::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->whereNull('archived_at');
        if ($customer !== null) {
            $query->where('customer_id', $customer->id);
        }

        $best = null;
        $bestScore = 0.0;
        foreach ($query->get() as $project) {
            $score = $this->similarity($needle, $this->normalize($project->name));
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $project;
            }
        }

        return $bestScore >= self::SUGGEST_THRESHOLD ? $best : null;
    }

    protected function alreadyImported(Organization $organization, string $entryKey): bool {
        return ExternalReference::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', $this->pluginId())
            ->where('external_type', self::EXT_TYPE_ENTRY)
            ->where('external_id', $entryKey)
            ->exists();
    }

    protected function createTimeEntry(Organization $organization, Project $project, ImportedTimeEntry $entry, int $userId, bool $defaultBillable): TimeEntry {
        $description = trim(implode(' — ', array_filter([
            $entry->projectName,
            $entry->activity,
            $entry->description,
        ]))) ?: $this->fallbackDescription();

        $timeEntry = TimeEntry::query()->create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'user_id' => $userId,
            'date' => $entry->startedAt->toDateString(),
            'started_at' => $entry->startedAt,
            'ended_at' => $entry->endedAt,
            'kind' => TimeEntryKind::Work,
            'description' => $description,
            'billable' => $defaultBillable && $entry->billable,
        ]);

        ExternalReference::query()->create([
            'organization_id' => $organization->id,
            'plugin_id' => $this->pluginId(),
            'external_type' => self::EXT_TYPE_ENTRY,
            'referenceable_type' => $timeEntry->getMorphClass(),
            'referenceable_id' => $timeEntry->getKey(),
            'external_id' => $entry->entryKey,
            'payload' => [
                'source' => $entry->source,
                'client' => $entry->clientName,
                'project' => $entry->projectName,
                'activity' => $entry->activity,
            ],
            'synced_at' => now(),
        ]);

        // API-Quelle: numerische Fremd-IDs merken — Export-Mapping und
        // umbenennungsfestes Matching künftiger Importe.
        if ($entry->projectId !== null) {
            $this->rememberReference($organization, self::EXT_TYPE_PROJECT_ID, (string) $entry->projectId, $project);
        }
        if ($entry->clientId !== null && $project->customer !== null) {
            $this->rememberReference($organization, self::EXT_TYPE_CLIENT_ID, (string) $entry->clientId, $project->customer);
        }

        return $timeEntry;
    }

    protected function recordPending(Organization $organization, ImportedTimeEntry $entry): void {
        $dedupeKey = self::EXT_TYPE_ENTRY . ':' . $entry->entryKey;

        $exists = IntegrationInboxItem::query()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', $this->pluginId())
            ->where('dedupe_key', $dedupeKey)
            ->exists();
        if ($exists) {
            return;
        }

        $client = trim((string) $entry->clientName);
        $project = trim((string) $entry->projectName);

        IntegrationInboxItem::query()->create([
            'organization_id' => $organization->id,
            'plugin_id' => $this->pluginId(),
            'source' => $entry->source,
            'target_type' => (new TimeEntry)->getMorphClass(),
            'external_type' => self::EXT_TYPE_ENTRY,
            'external_id' => $entry->entryKey,
            'dedupe_key' => $dedupeKey,
            'group_key' => $this->projectKey($client, $project, $entry->activity),
            'case_type' => IntegrationInboxItem::CASE_UNMATCHED,
            'status' => IntegrationInboxItem::STATUS_OPEN,
            'remote_snapshot' => [
                'source' => $entry->source,
                'entry_key' => $entry->entryKey,
                'client_name' => $entry->clientName,
                'project_name' => $entry->projectName,
                'activity' => $entry->activity,
                'description' => $entry->description,
                'started_at' => $entry->startedAt->toIso8601String(),
                'ended_at' => $entry->endedAt->toIso8601String(),
                'billable' => $entry->billable,
                'user_email' => $entry->userEmail,
                'tags' => $entry->tags,
                'client_id' => $entry->clientId,
                'project_id' => $entry->projectId,
                'activity_id' => $entry->activityId,
            ],
            'display_title' => $project !== '' ? $project : (string) __('(ohne Projekt)'),
            'display_subtitle' => $client !== '' ? $client : null,
            'occurred_at' => $entry->startedAt,
        ]);
    }

    /**
     * @return Collection<int, array{group_key: string, client_name: ?string, project_name: ?string, count: int, minutes: int, first_seen: ?\Illuminate\Support\Carbon, last_seen: ?\Illuminate\Support\Carbon}>
     */
    public function openInboxGroups(Organization $organization): Collection {
        return $this->openInboxItems($organization)
            ->groupBy('group_key')
            ->map(function ($group, $groupKey): array {
                /** @var Collection<int, IntegrationInboxItem> $group */
                $first = $group->first();
                $snap = $first !== null ? $first->remote_snapshot : [];
                /** @var \Illuminate\Support\Carbon|null $firstSeen */
                $firstSeen = $group->min('occurred_at');
                /** @var \Illuminate\Support\Carbon|null $lastSeen */
                $lastSeen = $group->max('occurred_at');

                return [
                    'group_key' => (string) $groupKey,
                    'client_name' => isset($snap['client_name']) ? (string) $snap['client_name'] : null,
                    'project_name' => isset($snap['project_name']) ? (string) $snap['project_name'] : null,
                    'count' => $group->count(),
                    'minutes' => (int) $group->sum(fn (IntegrationInboxItem $i): int => $this->snapshotMinutes($i->remote_snapshot ?? [])),
                    'first_seen' => $firstSeen,
                    'last_seen' => $lastSeen,
                ];
            })
            ->values();
    }

    /**
     * @return array{created: int, skipped: int}
     */
    public function bookInboxGroup(Organization $organization, string $groupKey, ?Customer $customer, Project $project, ?int $userId = null): array {
        $config = $this->resolveConfig($organization->id);
        $userId ??= $this->resolveBookingUserId($organization, isset($config['default_user_id']) && is_numeric($config['default_user_id']) ? (int) $config['default_user_id'] : null);
        if ($userId === null) {
            return ['created' => 0, 'skipped' => 0];
        }

        $items = $this->openInboxItems($organization)->where('group_key', $groupKey)->values();
        if ($items->isEmpty()) {
            return ['created' => 0, 'skipped' => 0];
        }

        $firstSnap = $items->first()->remote_snapshot;
        $clientName = trim((string) ($firstSnap['client_name'] ?? ''));
        $projectName = trim((string) ($firstSnap['project_name'] ?? ''));

        if ($customer !== null && $clientName !== '') {
            $this->rememberReference($organization, self::EXT_TYPE_CLIENT, $clientName, $customer);
        }
        $this->rememberReference($organization, self::EXT_TYPE_PROJECT, $this->projectKey($clientName, $projectName), $project);

        $created = 0;
        $skipped = 0;
        foreach ($items as $item) {
            $entry = $this->entryFromSnapshot((array) $item->remote_snapshot);

            if ($this->alreadyImported($organization, $entry->entryKey)) {
                $this->resolveItem($item, IntegrationInboxItem::STATUS_RESOLVED_LINKED, null);
                $skipped++;

                continue;
            }

            $timeEntry = $this->createTimeEntry($organization, $project, $entry, $userId, (bool) ($config['default_billable'] ?? true));
            $this->resolveItem($item, IntegrationInboxItem::STATUS_RESOLVED_CREATED, $timeEntry);
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    public function dismissInboxGroup(Organization $organization, string $groupKey): int {
        $items = $this->openInboxItems($organization)->where('group_key', $groupKey);
        foreach ($items as $item) {
            $this->resolveItem($item, IntegrationInboxItem::STATUS_DISMISSED, null);
        }

        return $items->count();
    }

    /**
     * @return Collection<int, IntegrationInboxItem>
     */
    protected function openInboxItems(Organization $organization): Collection {
        return IntegrationInboxItem::query()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', $this->pluginId())
            ->where('status', IntegrationInboxItem::STATUS_OPEN)
            ->whereNotNull('group_key')
            ->orderByDesc('occurred_at')
            ->get();
    }

    protected function resolveItem(IntegrationInboxItem $item, string $status, ?TimeEntry $timeEntry): void {
        $item->update([
            'status' => $status,
            'resolved_to_type' => $timeEntry?->getMorphClass(),
            'resolved_to_id' => $timeEntry?->getKey(),
            'resolved_by' => Auth::id(),
            'resolved_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $snap
     */
    protected function entryFromSnapshot(array $snap): ImportedTimeEntry {
        /** @var list<string> $tags */
        $tags = isset($snap['tags']) && is_array($snap['tags']) ? array_values(array_map('strval', $snap['tags'])) : [];

        return new ImportedTimeEntry(
            entryKey: (string) ($snap['entry_key'] ?? ''),
            clientName: $snap['client_name'] ?? null,
            projectName: $snap['project_name'] ?? null,
            activity: $snap['activity'] ?? null,
            description: $snap['description'] ?? null,
            startedAt: CarbonImmutable::parse((string) $snap['started_at']),
            endedAt: CarbonImmutable::parse((string) $snap['ended_at']),
            billable: (bool) ($snap['billable'] ?? false),
            userEmail: $snap['user_email'] ?? null,
            tags: $tags,
            source: (string) ($snap['source'] ?? ImportedTimeEntry::SOURCE_CSV),
            clientId: is_numeric($snap['client_id'] ?? null) ? (int) $snap['client_id'] : null,
            projectId: is_numeric($snap['project_id'] ?? null) ? (int) $snap['project_id'] : null,
            activityId: is_numeric($snap['activity_id'] ?? null) ? (int) $snap['activity_id'] : null,
        );
    }

    /**
     * @param  array<string, mixed>  $snap
     */
    protected function snapshotMinutes(array $snap): int {
        $start = $snap['started_at'] ?? null;
        $end = $snap['ended_at'] ?? null;
        if (! is_string($start) || ! is_string($end) || $start === '' || $end === '') {
            return 0;
        }

        return (int) round(CarbonImmutable::parse($start)->diffInSeconds(CarbonImmutable::parse($end)) / 60);
    }

    protected function rememberReference(Organization $organization, string $type, string $externalId, Model $referenceable): void {
        ExternalReference::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'plugin_id' => $this->pluginId(),
                'external_type' => $type,
                'external_id' => $externalId,
            ],
            [
                'referenceable_type' => $referenceable->getMorphClass(),
                'referenceable_id' => $referenceable->getKey(),
                'synced_at' => now(),
            ],
        );
    }

    protected function resolveBookingUserId(Organization $organization, ?int $defaultUserId): ?int {
        if ($defaultUserId !== null) {
            $user = User::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->whereKey($defaultUserId)->first();
            if ($user !== null) {
                return (int) $user->id;
            }
        }

        if ($organization->owner_id !== null) {
            return (int) $organization->owner_id;
        }

        $first = User::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->orderBy('id')->first();

        return $first !== null ? (int) $first->id : null;
    }

    /** Stabiler Gruppen-/Referenz-Schlüssel (Kunde|Projekt[|Tätigkeit], case-insensitiv). */
    protected function projectKey(?string $clientName, ?string $projectName, ?string $activity = null): string {
        $parts = [trim((string) $clientName), trim((string) $projectName)];
        if ($activity !== null && trim($activity) !== '') {
            $parts[] = trim($activity);
        }

        return mb_strtolower(implode('|', $parts));
    }

    protected function normalize(?string $value): string {
        $value = mb_strtolower(trim((string) $value));

        return (string) preg_replace('/\s+/', ' ', $value);
    }

    protected function similarity(string $a, string $b): float {
        if ($a === '' || $b === '') {
            return 0.0;
        }
        if ($a === $b) {
            return 1.0;
        }
        similar_text($a, $b, $percent);

        return $percent / 100;
    }
}
