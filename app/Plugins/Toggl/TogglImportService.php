<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TogglImportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Toggl;

use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\{Customer, ExternalReference, IntegrationInboxItem, Organization, Project, TimeEntry, User};
use App\Plugins\Toggl\Sources\{TogglApiClient, TogglCsvParser, TogglEntry};
use Carbon\CarbonImmutable;

/**
 * Kernlogik des Toggl-Imports:
 *  - Zeiteinträge aus der Toggl-API ({@see importFromApi()}) oder einem
 *    Detailed-Report-CSV ({@see importFromCsv()}) einlesen.
 *  - Toggl-Client → Kunde und Toggl-Projekt → Projekt ausschließlich über
 *    bestehende {@see ExternalReference} bzw. Namensgleichheit matchen
 *    (kein Auto-Anlegen). Treffer → TimeEntry (idempotent über die
 *    `entry`-Reference); kein Treffer → universelle Zuordnungs-Inbox
 *    ({@see \App\Models\IntegrationInboxItem}, gruppiert nach Client + Projekt).
 *  - Die Inbox bucht Gruppen gegen einen Kunden + Projekt ({@see bookInboxGroup()}),
 *    persistiert dabei die `client`/`project`-Reference (→ Folgeimporte matchen
 *    automatisch) und materialisiert die Einträge.
 */
class TogglImportService {
    public const EXT_TYPE_CLIENT = 'client';

    public const EXT_TYPE_PROJECT = 'project';

    public const EXT_TYPE_ENTRY = 'entry';

    /**
     * Ähnlichkeitsschwelle (0..1), ab der ein Toggl-Name in der Inbox als
     * Vorschlag für einen bestehenden Kunden/Projekt vorausgewählt wird.
     * Bewusst hoch: Vorschläge dürfen nie automatisch buchen, nur vorbelegen.
     */
    public const SUGGEST_THRESHOLD = 0.82;

    public function __construct(private readonly TogglCsvParser $csvParser = new TogglCsvParser) {}

    /**
     * Holt die Zeiteinträge der Toggl-API im Fenster [$from, $to] und verarbeitet sie.
     *
     * @param  array<string, mixed>  $config  Ergebnis von {@see TogglConfig::resolve()}
     * @return array{created: int, skipped: int, unmatched: int}
     */
    public function importFromApi(Organization $organization, array $config, CarbonImmutable $from, CarbonImmutable $to): array {
        $client = new TogglApiClient($config['api_token'], $config['base_url'], $config['workspace_id']);
        if (! $client->isConfigured()) {
            return ['created' => 0, 'skipped' => 0, 'unmatched' => 0];
        }

        return $this->ingest($organization, $client->fetchEntries($from, $to), $config);
    }

    /**
     * Verarbeitet einen Toggl-Detailed-Report-CSV-Inhalt.
     *
     * @param  array<string, mixed>  $config  Ergebnis von {@see TogglConfig::resolve()}
     * @return array{created: int, skipped: int, unmatched: int}
     */
    public function importFromCsv(Organization $organization, string $csvContent, array $config): array {
        return $this->ingest($organization, $this->csvParser->parse($csvContent), $config);
    }

    /**
     * @param  array<int, TogglEntry>  $entries
     * @param  array<string, mixed>  $config
     * @return array{created: int, skipped: int, unmatched: int}
     */
    private function ingest(Organization $organization, array $entries, array $config): array {
        $created = 0;
        $skipped = 0;
        $unmatched = 0;

        $userId = $this->resolveBookingUserId($organization, $config['default_user_id'] ?? null);
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

            $this->createTimeEntry($organization, $project, $entry, $userId, (bool) $config['default_billable']);
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped, 'unmatched' => $unmatched];
    }

    /**
     * Matcht den Toggl-Client eines Eintrags auf einen Kunden — zuerst über die
     * gespeicherte `client`-Reference, sonst über Name/Firma (case-insensitiv).
     */
    public function matchCustomer(Organization $organization, ?string $clientName): ?Customer {
        $clientName = $clientName !== null ? trim($clientName) : '';
        if ($clientName === '') {
            return null;
        }

        $ref = ExternalReference::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', TogglPlugin::ID)
            ->where('external_type', self::EXT_TYPE_CLIENT)
            ->where('external_id', $clientName)
            ->first();

        if ($ref?->referenceable instanceof Customer) {
            return $ref->referenceable;
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

    /**
     * Matcht den Toggl-Eintrag auf ein Projekt — über die `project`-Reference,
     * sonst über den Projektnamen (innerhalb des gematchten Kunden, sofern bekannt).
     */
    public function matchProject(Organization $organization, TogglEntry $entry): ?Project {
        $projectName = $entry->projectName !== null ? trim($entry->projectName) : '';
        if ($projectName === '') {
            return null;
        }

        $ref = ExternalReference::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', TogglPlugin::ID)
            ->where('external_type', self::EXT_TYPE_PROJECT)
            ->where('external_id', $this->projectKey($entry->clientName, $projectName))
            ->first();

        if ($ref?->referenceable instanceof Project) {
            return $ref->referenceable;
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

    /**
     * Fuzzy-Vorschlag: bester bestehender Kunde zum Toggl-Client-Namen (über
     * Name/Firma, normalisiert) — nur für die Inbox-Vorauswahl. Liefert null,
     * wenn kein Kandidat die {@see SUGGEST_THRESHOLD} erreicht.
     */
    public function suggestCustomer(Organization $organization, ?string $clientName): ?Customer {
        $needle = $this->normalize($clientName);
        if ($needle === '') {
            return null;
        }

        $best = null;
        $bestScore = 0.0;

        $customers = Customer::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereNull('archived_at')
            ->get();

        foreach ($customers as $customer) {
            $score = max(
                $this->similarity($needle, $this->normalize($customer->name)),
                $this->similarity($needle, $this->normalize($customer->company)),
            );
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $customer;
            }
        }

        return $bestScore >= self::SUGGEST_THRESHOLD ? $best : null;
    }

    /**
     * Fuzzy-Vorschlag: bestes bestehendes Projekt zum Toggl-Projektnamen,
     * optional auf den (vorgeschlagenen) Kunden eingeschränkt. Nur Vorauswahl.
     */
    public function suggestProject(Organization $organization, ?Customer $customer, ?string $projectName): ?Project {
        $needle = $this->normalize($projectName);
        if ($needle === '') {
            return null;
        }

        $query = Project::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereNull('archived_at');

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

    /**
     * Alle gemerkten Client-/Projekt-Zuordnungen der Organisation (für die
     * Mapping-Verwaltung), inkl. aufgelöstem Ziel.
     *
     * @return \Illuminate\Support\Collection<int, ExternalReference>
     */
    public function mappings(Organization $organization): \Illuminate\Support\Collection {
        return ExternalReference::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', TogglPlugin::ID)
            ->whereIn('external_type', [self::EXT_TYPE_CLIENT, self::EXT_TYPE_PROJECT])
            ->with('referenceable')
            ->orderBy('external_type')
            ->orderBy('external_id')
            ->get();
    }

    private function alreadyImported(Organization $organization, string $entryKey): bool {
        return ExternalReference::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', TogglPlugin::ID)
            ->where('external_type', self::EXT_TYPE_ENTRY)
            ->where('external_id', $entryKey)
            ->exists();
    }

    private function createTimeEntry(Organization $organization, Project $project, TogglEntry $entry, int $userId, bool $defaultBillable): TimeEntry {
        $description = trim(implode(' — ', array_filter([
            $entry->projectName,
            $entry->description,
        ]))) ?: (string) __('Toggl-Zeiteintrag');

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

        // Idempotenz-Anker: verknüpft den Toggl-Eintrag mit dem TimeEntry.
        ExternalReference::query()->create([
            'organization_id' => $organization->id,
            'plugin_id' => TogglPlugin::ID,
            'external_type' => self::EXT_TYPE_ENTRY,
            'referenceable_type' => $timeEntry->getMorphClass(),
            'referenceable_id' => $timeEntry->getKey(),
            'external_id' => $entry->entryKey,
            'payload' => [
                'source' => $entry->source,
                'client' => $entry->clientName,
                'project' => $entry->projectName,
            ],
            'synced_at' => now(),
        ]);

        return $timeEntry;
    }

    /**
     * Legt einen unmatchbaren Eintrag als offenen Eintrag in der universellen
     * Zuordnungs-Inbox ab (gruppiert nach Client + Projekt). Idempotent über den
     * entry_key (dedupe_key).
     */
    private function recordPending(Organization $organization, TogglEntry $entry): void {
        $dedupeKey = self::EXT_TYPE_ENTRY . ':' . $entry->entryKey;

        $exists = IntegrationInboxItem::query()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', TogglPlugin::ID)
            ->where('dedupe_key', $dedupeKey)
            ->exists();
        if ($exists) {
            return;
        }

        $client = trim((string) $entry->clientName);
        $project = trim((string) $entry->projectName);

        IntegrationInboxItem::query()->create([
            'organization_id' => $organization->id,
            'plugin_id' => TogglPlugin::ID,
            'source' => $entry->source,
            'target_type' => (new TimeEntry)->getMorphClass(),
            'external_type' => self::EXT_TYPE_ENTRY,
            'external_id' => $entry->entryKey,
            'dedupe_key' => $dedupeKey,
            'group_key' => $this->projectKey($client, $project),
            'case_type' => IntegrationInboxItem::CASE_UNMATCHED,
            'status' => IntegrationInboxItem::STATUS_OPEN,
            'remote_snapshot' => [
                'source' => $entry->source,
                'entry_key' => $entry->entryKey,
                'client_name' => $entry->clientName,
                'project_name' => $entry->projectName,
                'description' => $entry->description,
                'started_at' => $entry->startedAt->toIso8601String(),
                'ended_at' => $entry->endedAt->toIso8601String(),
                'billable' => $entry->billable,
                'user_email' => $entry->userEmail,
            ],
            'display_title' => $project !== '' ? $project : (string) __('(ohne Projekt)'),
            'display_subtitle' => $client !== '' ? $client : null,
            'occurred_at' => $entry->startedAt,
        ]);
    }

    /**
     * Offene Toggl-Inbox-Einträge der Organisation, gruppiert nach Client +
     * Projekt (group_key), für die Gruppen-Auflösung in der universellen Inbox.
     *
     * @return \Illuminate\Support\Collection<int, array{group_key: string, client_name: ?string, project_name: ?string, count: int, minutes: int, first_seen: ?\Illuminate\Support\Carbon, last_seen: ?\Illuminate\Support\Carbon}>
     */
    public function openInboxGroups(Organization $organization): \Illuminate\Support\Collection {
        return $this->openInboxItems($organization)
            ->groupBy('group_key')
            ->map(function ($group, $groupKey): array {
                /** @var \Illuminate\Support\Collection<int, IntegrationInboxItem> $group */
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
                    'minutes' => (int) $group->sum(fn(IntegrationInboxItem $i): int => $this->snapshotMinutes($i->remote_snapshot ?? [])),
                    'first_seen' => $firstSeen,
                    'last_seen' => $lastSeen,
                ];
            })
            ->values();
    }

    /**
     * Bucht alle offenen Inbox-Einträge einer Gruppe gegen Kunde + Projekt:
     * merkt die client-/project-Referenzen und materialisiert die Einträge als
     * TimeEntries (idempotent). Markiert die Items als aufgelöst.
     *
     * @return array{created: int, skipped: int}
     */
    public function bookInboxGroup(Organization $organization, string $groupKey, Customer $customer, Project $project, ?int $userId = null): array {
        $config = TogglConfig::resolve($organization->id);
        $userId ??= $this->resolveBookingUserId($organization, $config['default_user_id'] ?? null);
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

        // Referenzen merken, damit künftige Imports automatisch matchen.
        if ($clientName !== '') {
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

            $timeEntry = $this->createTimeEntry($organization, $project, $entry, $userId, (bool) $config['default_billable']);
            $this->resolveItem($item, IntegrationInboxItem::STATUS_RESOLVED_CREATED, $timeEntry);
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /** Verwirft alle offenen Inbox-Einträge einer Gruppe. */
    public function dismissInboxGroup(Organization $organization, string $groupKey): int {
        $items = $this->openInboxItems($organization)->where('group_key', $groupKey);
        foreach ($items as $item) {
            $this->resolveItem($item, IntegrationInboxItem::STATUS_DISMISSED, null);
        }

        return $items->count();
    }

    /**
     * @return \Illuminate\Support\Collection<int, IntegrationInboxItem>
     */
    private function openInboxItems(Organization $organization): \Illuminate\Support\Collection {
        return IntegrationInboxItem::query()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', TogglPlugin::ID)
            ->where('status', IntegrationInboxItem::STATUS_OPEN)
            ->whereNotNull('group_key')
            ->orderByDesc('occurred_at')
            ->get();
    }

    private function resolveItem(IntegrationInboxItem $item, string $status, ?TimeEntry $timeEntry): void {
        $item->update([
            'status' => $status,
            'resolved_to_type' => $timeEntry?->getMorphClass(),
            'resolved_to_id' => $timeEntry?->getKey(),
            'resolved_by' => \Illuminate\Support\Facades\Auth::id(),
            'resolved_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $snap
     */
    private function entryFromSnapshot(array $snap): TogglEntry {
        return new TogglEntry(
            source: (string) ($snap['source'] ?? 'api'),
            entryKey: (string) ($snap['entry_key'] ?? ''),
            clientName: $snap['client_name'] ?? null,
            projectName: $snap['project_name'] ?? null,
            description: $snap['description'] ?? null,
            startedAt: CarbonImmutable::parse((string) $snap['started_at']),
            endedAt: CarbonImmutable::parse((string) $snap['ended_at']),
            billable: (bool) ($snap['billable'] ?? false),
            userEmail: $snap['user_email'] ?? null,
        );
    }

    /**
     * Dauer eines Snapshot-Eintrags in Minuten (aus started_at/ended_at).
     *
     * @param  array<string, mixed>  $snap
     */
    private function snapshotMinutes(array $snap): int {
        $start = $snap['started_at'] ?? null;
        $end = $snap['ended_at'] ?? null;
        if (! is_string($start) || ! is_string($end) || $start === '' || $end === '') {
            return 0;
        }

        return (int) round(CarbonImmutable::parse($start)->diffInSeconds(CarbonImmutable::parse($end)) / 60);
    }

    private function rememberReference(Organization $organization, string $type, string $externalId, \Illuminate\Database\Eloquent\Model $referenceable): void {
        ExternalReference::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'plugin_id' => TogglPlugin::ID,
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

    /**
     * Bestimmt den Buchungs-Benutzer: konfigurierte default_user_id (in der Org)
     * → Org-Owner → erster Org-Benutzer. (Identisch zu RemoteSupport.)
     */
    private function resolveBookingUserId(Organization $organization, ?int $defaultUserId): ?int {
        if ($defaultUserId !== null) {
            $user = User::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->whereKey($defaultUserId)
                ->first();
            if ($user !== null) {
                return (int) $user->id;
            }
        }

        if ($organization->owner_id !== null) {
            return (int) $organization->owner_id;
        }

        $first = User::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->orderBy('id')
            ->first();

        return $first !== null ? (int) $first->id : null;
    }

    /** Stabiler Schlüssel für die Projekt-Reference (Client + Projektname, case-insensitiv). */
    private function projectKey(?string $clientName, ?string $projectName): string {
        return mb_strtolower(trim((string) $clientName) . '|' . trim((string) $projectName));
    }

    /** Normalisiert einen Namen für den Vergleich (lowercase, getrimmt, kollabierte Leerzeichen). */
    private function normalize(?string $value): string {
        $value = mb_strtolower(trim((string) $value));

        return (string) preg_replace('/\s+/', ' ', $value);
    }

    /** Ähnlichkeit zweier (bereits normalisierter) Strings als 0..1-Score. */
    private function similarity(string $a, string $b): float {
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
