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
use App\Models\{ExternalReference, IntegrationInboxItem, Organization, Project, TimeEntry};

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
 *
 * Fachlich zweigeteilt (B13): Matching/Vorschläge in
 * {@see MatchesTimeImportTargets}, Inbox-Fälle/Gruppen-Buchung in
 * {@see BooksTimeImportInboxGroups}; hier verbleibt der Ingest-Kern
 * (Pipeline, Fremdstand-Abgleich, TimeEntry-Anlage).
 */
abstract class MatchingTimeImportService {
    use AttachesImportedTags;
    use BooksTimeImportInboxGroups;
    use MatchesTimeImportTargets;
    use PersistsTimeImportInbox;
    use ReconcilesRemoteDeletions;

    public const EXT_TYPE_CLIENT = 'client';

    public const EXT_TYPE_PROJECT = 'project';

    public const EXT_TYPE_ENTRY = 'entry';

    /** Numerische Fremd-IDs (nur API-Quelle) — Grundlage des Export-Mappings. */
    public const EXT_TYPE_CLIENT_ID = 'client_id';

    public const EXT_TYPE_PROJECT_ID = 'project_id';

    /** Quell-E-Mail → Benutzer (gemerkte Zuordnung, z. B. abweichende Toggl-Adresse). */
    public const EXT_TYPE_USER_EMAIL = 'user_email';

    public const SUGGEST_THRESHOLD = 0.82;

    /** Max. Einträge der Gruppen-Vorschau in der Zuordnungs-Inbox. */
    public const GROUP_PREVIEW_LIMIT = 15;

    /** Gruppen-Präfix offener Benutzer-Zuordnungsfälle (MVP-509). */
    public const PENDING_USER_GROUP_PREFIX = 'user|';

    /** Gruppen-Suffix, wenn die Quelle gar kein Benutzersignal liefert. */
    public const PENDING_USER_NO_SIGNAL = '(ohne-signal)';

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
     * @param  RemoteSyncWindow|null  $window  Nur bei vollständigem Lauf — Grundlage der Löschungserkennung
     * @return array{created: int, skipped: int, unmatched: int, unresolved_users: int, updated: int, conflicts: int, removed: int}
     */
    protected function ingest(Organization $organization, array $entries, array $config, ?RemoteSyncWindow $window = null): array {
        // Der Import darf keine Rückschreibung auslösen — die Einträge kommen ja
        // gerade von dort, und `syncKnownEntry()` schreibt lokal.
        return TimeWritebackObserver::suppressed(fn (): array => $this->ingestEntries($organization, $entries, $config, $window));
    }

    /**
     * Einbenutzer-Modus (MVP-509): nur wenn der Administrator ihn ausdrücklich
     * gewählt hat, dürfen Einträge ohne auflösbares Benutzersignal auf den
     * konfigurierten Standard-Benutzer gebucht werden. Sonst entsteht ein
     * offener Zuordnungsfall — nie eine stille Hauptbenutzer-Buchung.
     *
     * @param  array<string, mixed>  $config
     */
    protected function singleUserMode(array $config): bool {
        return (bool) ($config['single_user_mode'] ?? false);
    }

    /**
     * @param  array<int, ImportedTimeEntry>  $entries
     * @param  array<string, mixed>  $config
     * @return array{created: int, skipped: int, unmatched: int, unresolved_users: int, updated: int, conflicts: int, removed: int}
     */
    private function ingestEntries(Organization $organization, array $entries, array $config, ?RemoteSyncWindow $window): array {
        $created = 0;
        $skipped = 0;
        $unmatched = 0;
        $unresolvedUsers = 0;
        $updated = 0;
        $conflicts = 0;

        $singleUser = $this->singleUserMode($config);
        $userId = $this->resolveBookingUserId($organization, isset($config['default_user_id']) && is_numeric($config['default_user_id']) ? (int) $config['default_user_id'] : null);
        if ($userId === null) {
            return ['created' => 0, 'skipped' => 0, 'unmatched' => 0, 'unresolved_users' => 0, 'updated' => 0, 'conflicts' => 0, 'removed' => 0];
        }

        foreach ($entries as $entry) {
            // Schlüsselwechsel (MVP-509: E-Mail floss in den CSV-Hash ein):
            // Bestandsreferenzen/-fälle unter dem Alt-Schlüssel auf den neuen
            // migrieren, bevor die Dedupe greift — sonst Duplikate beim Re-Import.
            if ($entry->legacyEntryKey !== null && $entry->legacyEntryKey !== $entry->entryKey) {
                $this->migrateLegacyEntryKey($organization, $entry->legacyEntryKey, $entry->entryKey);
            }

            if ($this->alreadyImported($organization, $entry->entryKey)) {
                // Bekannter Eintrag: nicht blind überspringen, sondern gegen den
                // beim Import hinterlegten Fingerabdruck prüfen — sonst bleiben
                // Korrekturen im Fremdsystem hier für immer unsichtbar.
                match ($this->syncKnownEntry($organization, $entry)) {
                    'updated' => $updated++,
                    'conflict' => $conflicts++,
                    default => $skipped++,
                };

                continue;
            }

            $project = $this->matchProject($organization, $entry);
            if ($project === null) {
                $this->recordPending($organization, $entry);
                $unmatched++;

                continue;
            }

            // MVP-509: Benutzer deterministisch auflösen. Ohne Treffer (unbekannte
            // oder fehlende Quell-E-Mail) bucht der Lauf NICHT still auf den
            // Hauptbenutzer, sondern stellt den Eintrag sichtbar zur Zuordnung.
            $entryUserId = $this->resolveImportUser($organization, $entry->userEmail);
            if ($entryUserId === null && ! $singleUser) {
                $this->recordPendingUser($organization, $entry);
                $unresolvedUsers++;

                continue;
            }

            $this->createTimeEntry($organization, $project, $entry, $entryUserId ?? $userId, (bool) ($config['default_billable'] ?? true));
            $created++;
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'unmatched' => $unmatched,
            'unresolved_users' => $unresolvedUsers,
            'updated' => $updated,
            'conflicts' => $conflicts,
            'removed' => $this->reconcileRemoteDeletions(
                $organization,
                array_values(array_map(static fn (ImportedTimeEntry $entry): string => $entry->entryKey, $entries)),
                $window,
            ),
        ];
    }

    /**
     * Gleicht einen bereits importierten Eintrag mit dem aktuellen Fremdstand ab.
     *
     * Abgerechnete/exportierte Zeiten werden nicht mehr überschrieben — deren
     * Grundlage hängt an Belegen. Die Abweichung landet dann als Konflikt in der
     * Inbox, wo jemand entscheiden kann.
     *
     * @return 'unchanged'|'updated'|'conflict'
     */
    protected function syncKnownEntry(Organization $organization, ImportedTimeEntry $entry): string {
        $reference = ExternalReference::query()
            ->forPlugin($organization, $this->pluginId(), self::EXT_TYPE_ENTRY)
            ->forExternalId($entry->entryKey)
            ->first();

        $known = is_array($reference?->payload) ? (string) ($reference->payload['fingerprint'] ?? '') : '';
        $current = RemoteTimeFingerprint::of($entry);
        if ($reference === null || $known === '' || $known === $current) {
            return 'unchanged'; // Altbestand ohne Fingerabdruck bleibt unberührt
        }

        $timeEntry = $reference->referenceable;
        if (! $timeEntry instanceof TimeEntry) {
            return 'unchanged';
        }

        if ($timeEntry->exported) {
            $this->recordRemoteChangeConflict($organization, $reference, $timeEntry, $entry);

            return 'conflict';
        }

        $timeEntry->forceFill([
            'started_at' => $entry->startedAt,
            'ended_at' => $entry->endedAt,
            'minutes' => $entry->minutes(),
            'description' => $entry->description,
        ])->save();

        // Tags additiv mitnehmen, wenn ohnehin ein Sync ansteht. Der
        // Fingerabdruck enthält Tags bewusst NICHT — reine Tag-Änderungen
        // drüben lösen kein Update aus, Entfernungen werden nie gespiegelt.
        $this->attachImportedTags($organization, $timeEntry, $entry->tags);

        $reference->payload = array_merge((array) $reference->payload, ['fingerprint' => $current]);
        $reference->synced_at = now();
        $reference->save();

        return 'updated';
    }

    /**
     * Fremde Änderung an einer bereits abgerechneten Zeit — sichtbar machen,
     * statt sie zu übernehmen oder zu verschlucken.
     */
    protected function recordRemoteChangeConflict(
        Organization $organization,
        ExternalReference $reference,
        TimeEntry $timeEntry,
        ImportedTimeEntry $entry,
    ): void {
        IntegrationInboxItem::query()->withoutGlobalScopes()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'plugin_id' => $this->pluginId(),
                'dedupe_key' => $this->pluginId() . '-remote-changed:' . $reference->external_id,
            ],
            [
                'source' => $this->pluginId(),
                'target_type' => TimeEntry::class,
                'external_type' => self::EXT_TYPE_ENTRY,
                'external_id' => (string) $reference->external_id,
                'case_type' => IntegrationInboxItem::CASE_CONFLICT,
                'referenceable_type' => $timeEntry->getMorphClass(),
                'referenceable_id' => $timeEntry->getKey(),
                'status' => IntegrationInboxItem::STATUS_OPEN,
                'remote_snapshot' => [
                    'reason' => 'remote_changed_after_export',
                    // Die Zeit hängt an einem Beleg: der Fremdstand darf hier
                    // nicht per Klick übernommen werden (GoBD). Bleibt nur
                    // Kenntnisnahme bzw. eine Korrektur außerhalb.
                    'resolution' => IntegrationInboxItem::RESOLUTION_ACKNOWLEDGE_ONLY,
                    'remote' => [
                        'started_at' => $entry->startedAt->toIso8601String(),
                        'ended_at' => $entry->endedAt->toIso8601String(),
                        'minutes' => $entry->minutes(),
                        'description' => $entry->description,
                    ],
                    'local' => [
                        'started_at' => $timeEntry->started_at?->toIso8601String(),
                        'ended_at' => $timeEntry->ended_at?->toIso8601String(),
                        'minutes' => $timeEntry->minutes,
                        'description' => $timeEntry->description,
                    ],
                ],
            ],
        );
    }

    protected function createTimeEntry(Organization $organization, Project $project, ImportedTimeEntry $entry, int $userId, bool $defaultBillable): TimeEntry {
        $description = trim(implode(' — ', array_filter([
            $entry->projectName,
            $entry->activity,
            $entry->description,
        ]))) ?: $this->fallbackDescription();

        $attributes = [
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            // Mitarbeiter-Zeile der Quelle gewinnt: E-Mail → Org-Benutzer,
            // sonst der Standard-/Buchungs-Benutzer.
            'user_id' => $this->resolveEntryUserId($organization, $entry->userEmail, $userId),
            'date' => $entry->startedAt->toDateString(),
            'started_at' => $entry->startedAt,
            'ended_at' => $entry->endedAt,
            'kind' => TimeEntryKind::Work,
            'description' => $description,
        ];
        if ($entry->billable !== null) {
            // Echtes Quell-Signal: default_billable bleibt der Riegel.
            $attributes['billable'] = $defaultBillable && $entry->billable;
        } elseif (! $defaultBillable) {
            // Hilfetext der Einstellung bleibt wahr: „Wenn aus, werden
            // importierte Zeiten nie als abrechenbar markiert."
            $attributes['billable'] = false;
        }
        // Sonst Attribut bewusst weglassen → TimeEntry-Boot erbt
        // effectiveBillable() des Projekts vor der Satz-Snapshot-Berechnung.

        $timeEntry = TimeEntry::query()->create($attributes);

        // Quell-Tags (Toggl-API/CSV, Kimai-/Clockify-CSV) additiv anhängen.
        $this->attachImportedTags($organization, $timeEntry, $entry->tags);

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
                // Fremd-IDs: die Rückrichtung adressiert damit (Workspace) und
                // bildet den Fingerabdruck identisch (Projekt).
                'project_id' => $entry->projectId,
                'workspace_id' => $entry->workspaceId,
                // Stand im Fremdsystem zum Importzeitpunkt — Grundlage für die
                // Konflikterkennung beim Zurückschreiben.
                'fingerprint' => RemoteTimeFingerprint::of($entry),
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

        // Offene Zuordnungsfälle dieses Eintrags schließen (z. B. Benutzer-Fall,
        // der durch eine inzwischen gepflegte Zuordnung buchbar wurde).
        $this->closePendingItems($organization, $entry->entryKey, $timeEntry);

        return $timeEntry;
    }
}
