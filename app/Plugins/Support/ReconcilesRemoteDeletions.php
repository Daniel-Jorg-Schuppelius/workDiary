<?php
/*
 * Created on   : Tue Jul 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReconcilesRemoteDeletions.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support;

use App\Models\{ExternalReference, IntegrationInboxItem, Organization, TimeEntry};

/**
 * Erkennt Zeiteinträge, die im Fremdsystem verschwunden sind — der Gegenpart
 * zum eintragsweisen Abgleich, der nur sieht, was geliefert wurde.
 *
 * Greift ausschließlich bei einem vollständigen Lauf ({@see RemoteSyncWindow})
 * und nie bei leerer Lieferung: eine API, die kurzzeitig nichts zurückgibt,
 * würde sonst den ganzen Zeitraum leerräumen. Nicht abgerechnete Zeiten werden
 * gelöscht, abgerechnete bleiben stehen und melden den Konflikt — ihre
 * Grundlage hängt an Belegen.
 */
trait ReconcilesRemoteDeletions {
    /**
     * @param  list<string>  $seenKeys  Idempotenz-Schlüssel aller gelieferten Einträge
     */
    protected function reconcileRemoteDeletions(Organization $organization, array $seenKeys, ?RemoteSyncWindow $window, string $externalType = 'entry'): int {
        if ($window === null || $seenKeys === []) {
            return 0;
        }

        $orphans = ExternalReference::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', $this->pluginId())
            ->where('external_type', $externalType)
            ->where('referenceable_type', (new TimeEntry)->getMorphClass())
            ->whereNotIn('external_id', $seenKeys)
            ->whereIn('referenceable_id', TimeEntry::query()->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->whereBetween('date', [$window->from->toDateString(), $window->to->toDateString()])
                ->select('id'))
            ->get();

        $removed = 0;
        foreach ($orphans as $reference) {
            // CSV-Importe adressieren kein Fremdobjekt — ihr Fehlen in einem
            // API-Lauf heißt nicht, dass drüben etwas gelöscht wurde.
            if (RemoteEntryKey::externalId((string) $reference->external_id) === null) {
                continue;
            }

            $timeEntry = $reference->referenceable;
            if (! $timeEntry instanceof TimeEntry) {
                continue;
            }

            if ($timeEntry->exported) {
                $this->recordRemoteDeletionConflict($organization, $reference, $timeEntry, $externalType);

                continue;
            }

            $timeEntry->delete();
            $reference->delete();
            $removed++;
        }

        return $removed;
    }

    /** Drüben gelöscht, hier bereits abgerechnet — sichtbar machen statt löschen. */
    protected function recordRemoteDeletionConflict(Organization $organization, ExternalReference $reference, TimeEntry $timeEntry, string $externalType): void {
        IntegrationInboxItem::query()->withoutGlobalScopes()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'plugin_id' => $this->pluginId(),
                'dedupe_key' => $this->pluginId() . '-remote-deleted:' . $reference->external_id,
            ],
            [
                'source' => $this->pluginId(),
                'target_type' => TimeEntry::class,
                'external_type' => $externalType,
                'external_id' => (string) $reference->external_id,
                'case_type' => IntegrationInboxItem::CASE_CONFLICT,
                'referenceable_type' => $timeEntry->getMorphClass(),
                'referenceable_id' => $timeEntry->getKey(),
                'status' => IntegrationInboxItem::STATUS_OPEN,
                'remote_snapshot' => [
                    'reason' => 'remote_deleted_after_export',
                    // Die Zeit hängt an einem Beleg: der Fremdstand darf hier
                    // nicht per Klick übernommen werden (GoBD). Bleibt nur
                    // Kenntnisnahme bzw. eine Korrektur außerhalb.
                    'resolution' => IntegrationInboxItem::RESOLUTION_ACKNOWLEDGE_ONLY,
                    'local' => [
                        'date' => $timeEntry->date?->toDateString(),
                        'started_at' => $timeEntry->started_at?->toIso8601String(),
                        'ended_at' => $timeEntry->ended_at?->toIso8601String(),
                        'minutes' => $timeEntry->minutes,
                        'description' => $timeEntry->description,
                    ],
                ],
            ],
        );
    }
}
