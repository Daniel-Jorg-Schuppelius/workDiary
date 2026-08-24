<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BooksTimeImportInboxGroups.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support;

use App\Models\{Customer, ExternalReference, ExternalReferenceAlias, ForeignCustomer, IntegrationInboxItem, Organization, Project, TimeEntry, User};
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Inbox-Hälfte der {@see MatchingTimeImportService}-Pipeline: offene
 * Zuordnungsfälle anlegen und gruppieren (Projekt- wie Benutzer-Fälle,
 * MVP-509), Gruppen-Vorschau, Buchung/Verwerfen ganzer Gruppen sowie die
 * Migration von Idempotenz-Schlüsseln. Konstanten (PENDING_USER_*,
 * GROUP_PREVIEW_LIMIT, EXT_TYPE_*) und Buchungs-Hooks liefert die
 * komponierende Klasse.
 */
trait BooksTimeImportInboxGroups {
    /** Plugin-Id, unter der References/Inbox-Items abgelegt werden. */
    abstract protected function pluginId(): string;

    /**
     * Migriert die Artefakte eines Eintrags vom Alt- auf den neuen
     * Idempotenz-Schlüssel (Referenz, Alias, offene Inbox-Fälle) — nur wenn der
     * neue Schlüssel noch nicht belegt ist. Der Snapshot offener Fälle wird
     * mitgezogen, damit eine spätere Inbox-Buchung die Referenz unter dem
     * neuen Schlüssel anlegt.
     */
    protected function migrateLegacyEntryKey(Organization $organization, string $legacyKey, string $newKey): void {
        if (! $this->alreadyImported($organization, $newKey) && $this->alreadyImported($organization, $legacyKey)) {
            ExternalReference::query()
                ->forPlugin($organization, $this->pluginId(), $this->entryExternalType())
                ->forExternalId($legacyKey)
                ->update(['external_id' => $newKey]);
            ExternalReferenceAlias::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->where('plugin_id', $this->pluginId())
                ->where('external_type', $this->entryExternalType())
                ->where('external_id', $legacyKey)
                ->update(['external_id' => $newKey]);
        }

        $legacyDedupe = $this->entryExternalType() . ':' . $legacyKey;
        $newDedupe = $this->entryExternalType() . ':' . $newKey;
        $hasNewItem = IntegrationInboxItem::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', $this->pluginId())
            ->where('dedupe_key', $newDedupe)
            ->exists();
        if ($hasNewItem) {
            return;
        }

        $items = IntegrationInboxItem::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', $this->pluginId())
            ->where('dedupe_key', $legacyDedupe)
            ->where('status', IntegrationInboxItem::STATUS_OPEN)
            ->get();
        foreach ($items as $item) {
            $snapshot = (array) ($item->remote_snapshot ?? []);
            $snapshot['entry_key'] = $newKey;
            $item->update([
                'dedupe_key' => $newDedupe,
                'external_id' => $newKey,
                'remote_snapshot' => $snapshot,
            ]);
        }
    }

    /**
     * Offener Zuordnungsfall „unbekannter Quell-Benutzer" (MVP-509): das
     * Projekt ist auflösbar, aber die Quell-E-Mail passt zu keinem Benutzer
     * (oder fehlt). Gruppiert je E-Mail, damit EINE Zuordnung die ganze
     * Gruppe buchbar macht.
     */
    protected function recordPendingUser(Organization $organization, ImportedTimeEntry $entry): void {
        $email = mb_strtolower(trim((string) $entry->userEmail));
        $groupKey = self::PENDING_USER_GROUP_PREFIX . ($email !== '' ? $email : self::PENDING_USER_NO_SIGNAL);

        $this->recordPendingItem($organization, $entry->entryKey, [
            'source' => $entry->source,
            'group_key' => $groupKey,
            'remote_snapshot' => $this->pendingSnapshot($entry) + ['pending_reason' => 'user'],
            'display_title' => $email !== ''
                ? (string) __('Unbekannter Benutzer: :email', ['email' => $email])
                : (string) __('Eintrag ohne Benutzersignal'),
            'display_subtitle' => trim((string) $entry->projectName) !== '' ? trim((string) $entry->projectName) : null,
            'occurred_at' => $entry->startedAt,
        ]);
    }

    /**
     * Schließt offene Inbox-Fälle eines Eintrags, sobald er (auf welchem Weg
     * auch immer) gebucht wurde — sonst bliebe z. B. ein Benutzer-Fall offen,
     * obwohl der Folgelauf nach gepflegter Zuordnung längst gebucht hat.
     */
    protected function closePendingItems(Organization $organization, string $entryKey, TimeEntry $timeEntry): void {
        $items = IntegrationInboxItem::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', $this->pluginId())
            ->where('dedupe_key', $this->entryExternalType() . ':' . $entryKey)
            ->where('status', IntegrationInboxItem::STATUS_OPEN)
            ->get();
        foreach ($items as $item) {
            $this->resolveItem($item, IntegrationInboxItem::STATUS_RESOLVED_CREATED, $timeEntry);
        }
    }

    /**
     * Gemeinsamer Snapshot offener Inbox-Fälle (Projekt- wie Benutzer-Fälle).
     *
     * @return array<string, mixed>
     */
    private function pendingSnapshot(ImportedTimeEntry $entry): array {
        return [
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
            'workspace_id' => $entry->workspaceId,
            'workspace_name' => $entry->workspaceName,
        ];
    }

    protected function recordPending(Organization $organization, ImportedTimeEntry $entry): void {
        $client = trim((string) $entry->clientName);
        $project = trim((string) $entry->projectName);

        // Workspace-Präfix trennt die Inbox-Gruppen je Quell-Workspace —
        // sonst mischen sich z. B. „(ohne Projekt)"-Einträge verschiedener
        // Workspaces in einer Gruppe. Nur Gruppenschlüssel; die gemerkten
        // Zuordnungs-Schlüssel (rememberReference) bleiben namensbasiert.
        $groupKey = ($entry->workspaceId !== null ? 'ws' . $entry->workspaceId . '|' : '')
            . $this->projectKey($client, $project, $entry->activity);

        $this->recordPendingItem($organization, $entry->entryKey, [
            'source' => $entry->source,
            'group_key' => $groupKey,
            'remote_snapshot' => $this->pendingSnapshot($entry),
            'display_title' => $project !== '' ? $project : (string) __('(ohne Projekt)'),
            'display_subtitle' => $client !== '' ? $client : null,
            'occurred_at' => $entry->startedAt,
        ]);
    }

    /**
     * @return Collection<int, array{group_key: string, client_name: ?string, project_name: ?string, workspace_name: ?string, count: int, minutes: int, first_seen: ?\Illuminate\Support\Carbon, last_seen: ?\Illuminate\Support\Carbon, entries: array<int, array{description: ?string, started_at: ?string, ended_at: ?string, minutes: int, user_email: ?string, billable: ?bool}>, entries_more: int}>
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
                            'billable' => isset($s['billable']) ? (bool) $s['billable'] : null,
                        ];
                    })
                    ->values()
                    ->all();

                return [
                    'group_key' => (string) $groupKey,
                    'client_name' => isset($snap['client_name']) ? (string) $snap['client_name'] : null,
                    'project_name' => isset($snap['project_name']) ? (string) $snap['project_name'] : null,
                    'workspace_name' => isset($snap['workspace_name']) && (string) $snap['workspace_name'] !== ''
                        ? (string) $snap['workspace_name']
                        : (isset($snap['workspace_id']) ? 'Workspace ' . $snap['workspace_id'] : null),
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

    /** Ist der Gruppen-Schlüssel ein offener Benutzer-Zuordnungsfall (MVP-509)? */
    public function isUserGroupKey(string $groupKey): bool {
        return str_starts_with($groupKey, self::PENDING_USER_GROUP_PREFIX);
    }

    /**
     * Bucht eine offene Inbox-Gruppe.
     *
     * Projekt-Gruppen (Kunde|Projekt): merkt die Referenzen und bucht gegen das
     * übergebene Projekt. Benutzer-Gruppen (`user|<email>`, MVP-509): das
     * Projekt ist je Eintrag bereits auflösbar; ein explizit gewählter
     * Benutzer wird als E-Mail-Zuordnung gemerkt, sodass Folgeimporte
     * automatisch treffen. Ohne auflösbaren Benutzer bleibt ein Eintrag im
     * Mehrbenutzer-Modus offen — nie eine stille Hauptbenutzer-Buchung.
     *
     * @return array{created: int, skipped: int}
     */
    public function bookInboxGroup(Organization $organization, string $groupKey, ?Customer $customer, ?Project $project, ?int $userId = null, ?ForeignCustomer $foreignCustomer = null): array {
        $config = $this->resolveConfig($organization->id);
        $singleUser = $this->singleUserMode($config);
        $isUserGroup = $this->isUserGroupKey($groupKey);

        // Explizite Benutzer-Wahl strikt org-gebunden — eine manipulierte
        // Fremd-ID darf nie zum Buchungs-Benutzer werden (Cross-Tenant).
        $explicitUser = $userId !== null
            ? User::query()->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->whereNull('customer_id')
                ->whereNull('deactivated_at')
                ->whereKey($userId)
                ->first()
            : null;
        $explicitUserId = $explicitUser?->id !== null ? (int) $explicitUser->id : null;

        $userId = $explicitUserId
            ?? $this->resolveBookingUserId($organization, isset($config['default_user_id']) && is_numeric($config['default_user_id']) ? (int) $config['default_user_id'] : null);
        if ($userId === null) {
            return ['created' => 0, 'skipped' => 0];
        }

        $items = $this->openInboxItems($organization)->where('group_key', $groupKey)->values();
        if ($items->isEmpty()) {
            return ['created' => 0, 'skipped' => 0];
        }

        if ($isUserGroup) {
            // Explizite Benutzer-Wahl als Zuordnung merken — die eigentliche
            // Auflösung läuft dann einheitlich über resolveEntryUserId().
            $email = substr($groupKey, strlen(self::PENDING_USER_GROUP_PREFIX));
            if ($explicitUser !== null && $email !== '' && $email !== self::PENDING_USER_NO_SIGNAL) {
                $this->rememberUserEmail($organization, $email, $explicitUser);
            }
        } else {
            $firstSnap = $items->first()->remote_snapshot;
            $clientName = trim((string) ($firstSnap['client_name'] ?? ''));
            $projectName = trim((string) ($firstSnap['project_name'] ?? ''));

            // Client-Referenz: der Fremdkunde (Endkunde) ist der präzisere Schlüssel —
            // künftige Importe scopen Projekt-Matches dann auf ihn statt nur die Firma.
            if ($customer !== null && $clientName !== '') {
                $this->rememberReference($organization, self::EXT_TYPE_CLIENT, $clientName, $foreignCustomer ?? $customer);
            }
            if ($project !== null) {
                $this->rememberReference($organization, self::EXT_TYPE_PROJECT, $this->projectKey($clientName, $projectName), $project);
            }
        }

        $created = 0;
        $skipped = 0;
        foreach ($items as $item) {
            $entry = $this->entryFromSnapshot((array) $item->remote_snapshot);

            if ($this->alreadyImported($organization, $entry->entryKey)) {
                $this->resolveItem($item, IntegrationInboxItem::STATUS_RESOLVED_LINKED, null);
                $skipped++;

                continue;
            }

            // Benutzer je Eintrag: Quell-E-Mail gewinnt; explizite Wahl bzw.
            // Standard nur als bewusster Fallback (Einbenutzer-Modus oder
            // ausdrückliche Auswahl beim Buchen).
            $entryUserId = $this->resolveImportUser($organization, $entry->userEmail)
                ?? $explicitUserId
                ?? ($singleUser ? $userId : null);
            if ($entryUserId === null) {
                if (! $isUserGroup) {
                    // Projekt ist jetzt bekannt, nur der Benutzer fehlt: Fall in
                    // eine Benutzer-Gruppe umhängen statt still zu buchen.
                    $this->regroupAsPendingUser($item, $entry);
                }
                $skipped++;

                continue;
            }

            // Benutzer-Gruppen: das Projekt je Eintrag auflösen (war beim
            // Anlegen des Falls bereits eindeutig); Parameter nur als Fallback.
            $itemProject = $isUserGroup ? ($this->matchProject($organization, $entry) ?? $project) : $project;
            if ($itemProject === null) {
                // Referenz inzwischen weggefallen und kein Fallback — offen lassen.
                $skipped++;

                continue;
            }

            $timeEntry = $this->createTimeEntry($organization, $itemProject, $entry, $entryUserId, (bool) ($config['default_billable'] ?? true));
            $this->resolveItem($item, IntegrationInboxItem::STATUS_RESOLVED_CREATED, $timeEntry);
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /** Hängt ein offenes Item in die Benutzer-Gruppe seiner Quell-E-Mail um. */
    private function regroupAsPendingUser(IntegrationInboxItem $item, ImportedTimeEntry $entry): void {
        $email = mb_strtolower(trim((string) $entry->userEmail));
        $item->update([
            'group_key' => self::PENDING_USER_GROUP_PREFIX . ($email !== '' ? $email : self::PENDING_USER_NO_SIGNAL),
            'display_title' => $email !== ''
                ? (string) __('Unbekannter Benutzer: :email', ['email' => $email])
                : (string) __('Eintrag ohne Benutzersignal'),
            'display_subtitle' => trim((string) $entry->projectName) !== '' ? trim((string) $entry->projectName) : null,
        ]);
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
            // null (kein Quell-Signal, z. B. Toggl Free) muss den Roundtrip
            // überleben — sonst würde die Inbox-Buchung hart „nicht abrechenbar".
            billable: isset($snap['billable']) ? (bool) $snap['billable'] : null,
            userEmail: $snap['user_email'] ?? null,
            tags: $tags,
            source: (string) ($snap['source'] ?? ImportedTimeEntry::SOURCE_CSV),
            clientId: is_numeric($snap['client_id'] ?? null) ? (int) $snap['client_id'] : null,
            projectId: is_numeric($snap['project_id'] ?? null) ? (int) $snap['project_id'] : null,
            activityId: is_numeric($snap['activity_id'] ?? null) ? (int) $snap['activity_id'] : null,
            workspaceId: is_numeric($snap['workspace_id'] ?? null) ? (int) $snap['workspace_id'] : null,
            workspaceName: isset($snap['workspace_name']) ? (string) $snap['workspace_name'] : null,
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
}
