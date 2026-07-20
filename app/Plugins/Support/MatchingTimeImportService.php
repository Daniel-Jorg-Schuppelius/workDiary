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
use App\Models\{Customer, ExternalReference, ExternalReferenceAlias, ForeignCustomer, IntegrationInboxItem, Organization, Project, TimeEntry};
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

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
    use PersistsTimeImportInbox;

    public const EXT_TYPE_CLIENT = 'client';

    public const EXT_TYPE_PROJECT = 'project';

    public const EXT_TYPE_ENTRY = 'entry';

    /** Numerische Fremd-IDs (nur API-Quelle) — Grundlage des Export-Mappings. */
    public const EXT_TYPE_CLIENT_ID = 'client_id';

    public const EXT_TYPE_PROJECT_ID = 'project_id';

    public const SUGGEST_THRESHOLD = 0.82;

    /** Max. Einträge der Gruppen-Vorschau in der Zuordnungs-Inbox. */
    public const GROUP_PREVIEW_LIMIT = 15;

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
        // Client als Fremdkunde (Endkunde) gemerkt → dessen Firma ist der Kunde.
        if ($byName instanceof ForeignCustomer) {
            return $byName->customer;
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

        $client = $this->matchClientForEntry($organization, $entry);

        $query = Project::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($projectName)]);

        // Fremdkunde (Endkunde): gleichnamige Projekte verschiedener Endkunden
        // derselben Firma bleiben getrennt — daher zusätzlich auf ihn scopen.
        if ($client instanceof ForeignCustomer) {
            $query->where('customer_id', $client->customer_id)->where('foreign_customer_id', $client->id);
        } elseif ($client instanceof Customer) {
            $query->where('customer_id', $client->id);
        }

        return $query->first();
    }

    /**
     * Client-Auflösung im Projekt-Name-Fallback: gemerkte Referenz kann auf
     * einen Kunden oder Fremdkunden (Endkunden) zeigen. Hook für Plugins mit
     * zusätzlichen Schlüsseln (Toggl: stabile client_id).
     */
    protected function matchClientForEntry(Organization $organization, ImportedTimeEntry $entry): Customer|ForeignCustomer|null {
        $clientName = $entry->clientName !== null ? trim($entry->clientName) : '';
        if ($clientName !== '') {
            $byName = $this->resolveByReference($organization, self::EXT_TYPE_CLIENT, $clientName);
            if ($byName instanceof Customer || $byName instanceof ForeignCustomer) {
                return $byName;
            }
        }

        return $this->matchCustomer($organization, $entry->clientName);
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

    /**
     * Fuzzy-Vorschlag eines Fremdkunden (Endkunden) zum Toggl-/Import-Client:
     * gemerkte Client-Referenz zuerst (exakt), dann Namensähnlichkeit über alle
     * aktiven Fremdkunden der Organisation.
     */
    public function suggestForeignCustomer(Organization $organization, ?string $clientName): ?ForeignCustomer {
        $trimmed = $clientName !== null ? trim($clientName) : '';
        if ($trimmed === '') {
            return null;
        }

        $byReference = $this->resolveByReference($organization, self::EXT_TYPE_CLIENT, $trimmed);
        if ($byReference instanceof ForeignCustomer) {
            return $byReference;
        }

        $needle = $this->normalize($trimmed);
        $best = null;
        $bestScore = 0.0;
        foreach (ForeignCustomer::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->whereNull('archived_at')->get() as $foreign) {
            $score = max($this->similarity($needle, $this->normalize($foreign->name)), $this->similarity($needle, $this->normalize($foreign->company)));
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $foreign;
            }
        }

        return $bestScore >= self::SUGGEST_THRESHOLD ? $best : null;
    }

    public function suggestProject(Organization $organization, ?Customer $customer, ?string $projectName, ?ForeignCustomer $foreignCustomer = null): ?Project {
        $needle = $this->normalize($projectName);
        if ($needle === '') {
            return null;
        }

        $query = Project::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->whereNull('archived_at');
        if ($foreignCustomer !== null) {
            $query->where('foreign_customer_id', $foreignCustomer->id);
        } elseif ($customer !== null) {
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
        // umbenennungsfestes Matching künftiger Importe. Fremdkunde (Endkunde)
        // des Projekts hat Vorrang, sonst würde jede Auto-Buchung eine
        // Fremdkunden-Referenz wieder auf die Firma zurückdrehen.
        if ($entry->projectId !== null) {
            $this->rememberReference($organization, self::EXT_TYPE_PROJECT_ID, (string) $entry->projectId, $project);
        }
        $clientTarget = $project->foreignCustomer ?? $project->customer;
        if ($entry->clientId !== null && $clientTarget !== null) {
            $this->rememberReference($organization, self::EXT_TYPE_CLIENT_ID, (string) $entry->clientId, $clientTarget);
        }

        return $timeEntry;
    }

    protected function recordPending(Organization $organization, ImportedTimeEntry $entry): void {
        $client = trim((string) $entry->clientName);
        $project = trim((string) $entry->projectName);

        $this->recordPendingItem($organization, $entry->entryKey, [
            'source' => $entry->source,
            'group_key' => $this->projectKey($client, $project, $entry->activity),
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
     * @return Collection<int, array{group_key: string, client_name: ?string, project_name: ?string, count: int, minutes: int, first_seen: ?\Illuminate\Support\Carbon, last_seen: ?\Illuminate\Support\Carbon, entries: array<int, array{description: ?string, started_at: ?string, ended_at: ?string, minutes: int, user_email: ?string, billable: bool}>, entries_more: int}>
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

                // Vorschau der dahinterliegenden Einträge (chronologisch, gedeckelt) —
                // der Anwender sieht vor der Buchung, was er bucht.
                $entries = $group->sortBy('occurred_at')
                    ->take(self::GROUP_PREVIEW_LIMIT)
                    ->map(function (IntegrationInboxItem $item): array {
                        $s = (array) ($item->remote_snapshot ?? []);

                        return [
                            'description' => isset($s['description']) && (string) $s['description'] !== '' ? (string) $s['description'] : null,
                            'started_at' => isset($s['started_at']) ? (string) $s['started_at'] : null,
                            'ended_at' => isset($s['ended_at']) ? (string) $s['ended_at'] : null,
                            'minutes' => $this->snapshotMinutes($s),
                            'user_email' => isset($s['user_email']) && (string) $s['user_email'] !== '' ? (string) $s['user_email'] : null,
                            'billable' => (bool) ($s['billable'] ?? false),
                        ];
                    })
                    ->values()
                    ->all();

                return [
                    'group_key' => (string) $groupKey,
                    'client_name' => isset($snap['client_name']) ? (string) $snap['client_name'] : null,
                    'project_name' => isset($snap['project_name']) ? (string) $snap['project_name'] : null,
                    'count' => $group->count(),
                    'minutes' => (int) $group->sum(fn (IntegrationInboxItem $i): int => $this->snapshotMinutes($i->remote_snapshot ?? [])),
                    'first_seen' => $firstSeen,
                    'last_seen' => $lastSeen,
                    'entries' => $entries,
                    'entries_more' => max(0, $group->count() - count($entries)),
                ];
            })
            ->values();
    }

    /**
     * @return array{created: int, skipped: int}
     */
    public function bookInboxGroup(Organization $organization, string $groupKey, ?Customer $customer, Project $project, ?int $userId = null, ?ForeignCustomer $foreignCustomer = null): array {
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

        // Client-Referenz: der Fremdkunde (Endkunde) ist der präzisere Schlüssel —
        // künftige Importe scopen Projekt-Matches dann auf ihn statt nur die Firma.
        if ($customer !== null && $clientName !== '') {
            $this->rememberReference($organization, self::EXT_TYPE_CLIENT, $clientName, $foreignCustomer ?? $customer);
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
        $key = [
            'organization_id' => $organization->id,
            'plugin_id' => $this->pluginId(),
            'external_type' => $type,
            'external_id' => $externalId,
        ];
        $target = [
            'referenceable_type' => $referenceable->getMorphClass(),
            'referenceable_id' => $referenceable->getKey(),
        ];

        // extref_unique erlaubt je Plugin/Typ nur EINE Primär-Referenz pro
        // Zielmodell. Zeigt bereits ein anderer Schlüssel auf das Ziel (mehrere
        // Toggl-Projekte → ein Projekt, Merge, Umbenennung), wird dieser
        // Schlüssel als Alias gemerkt statt zu kollidieren.
        $targetTaken = ExternalReference::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', $this->pluginId())
            ->where('external_type', $type)
            ->where('referenceable_type', $target['referenceable_type'])
            ->where('referenceable_id', $target['referenceable_id'])
            ->where('external_id', '!=', $externalId)
            ->exists();

        if ($targetTaken) {
            // Veraltete Primär-Referenz DIESES Schlüssels (anderes Ziel) entfernen,
            // den Schlüssel als Alias aufs Ziel weiterleiten.
            ExternalReference::query()->withoutGlobalScopes()->where($key)->delete();
            ExternalReferenceAlias::query()->withoutGlobalScopes()->updateOrCreate($key, $target);

            return;
        }

        ExternalReference::query()->updateOrCreate($key, $target + ['synced_at' => now()]);
        // Ein früherer Alias desselben Schlüssels ist durch die Primär-Referenz überholt.
        ExternalReferenceAlias::query()->withoutGlobalScopes()->where($key)->delete();
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
