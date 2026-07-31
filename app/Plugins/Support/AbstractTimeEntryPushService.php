<?php
/*
 * Created on   : Thu Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AbstractTimeEntryPushService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support;

use App\Models\{ExternalReference, Organization, TimeEntry};
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Gemeinsames Skelett der Zeit-Export-Rückkanäle (OpenProject, Kimai, …):
 * Kandidaten-Query über gemappte Projekte, Idempotenz über die
 * `pushed_entry`-Reference, exportPending-Ergebnisschleife als
 * Template-Methode. Plugin-spezifisch bleiben Config-Validierung
 * ({@see prepareExport()}), Remote-Anlage ({@see createRemoteEntry()}),
 * Skip-Zusatzregeln ({@see shouldSkip()}) und die Abbruch-Bedingung
 * ({@see shouldAbort()}, z. B. Rate-Limit).
 */
abstract class AbstractTimeEntryPushService {
    public const EXT_TYPE_PUSHED = 'pushed_entry';

    /** Plugin-Id, unter der die pushed-References abgelegt werden. */
    abstract protected function pluginId(): string;

    /**
     * Config prüfen + Lauf vorbereiten (Client/Mappings); ein Fehlertext
     * beendet den Lauf ohne Kandidaten.
     *
     * @param  array<string, mixed>  $config
     */
    abstract protected function prepareExport(Organization $organization, array $config): ?string;

    /**
     * Projekt-IDs, deren Zeiten exportierbar sind (leer = keine Kandidaten,
     * kein Fehler).
     *
     * @return list<int>
     */
    abstract protected function exportableProjectIds(Organization $organization): array;

    /**
     * Legt den Remote-Eintrag an und liefert die externe ID für die
     * pushed-Reference. API-Fehler als Exception (→ {@see shouldAbort()} /
     * {@see isExpectedFailure()}).
     */
    abstract protected function createRemoteEntry(Organization $organization, TimeEntry $entry): string;

    /** Erwarteter API-Fehler je Eintrag → failed + Fehlermeldung statt Rethrow. */
    abstract protected function isExpectedFailure(\Throwable $e): bool;

    /** Zusätzliche Skip-Regel je Eintrag (z. B. Import-Echo-Guard). */
    protected function shouldSkip(Organization $organization, TimeEntry $entry): bool {
        return false;
    }

    /** Abbruch-Bedingung (z. B. Rate-Limit): true = Lauf beenden, Rest bleibt offen. */
    protected function shouldAbort(\Throwable $e): bool {
        return false;
    }

    /**
     * Plugin-spezifische Erweiterung der Kandidaten-Query (Eager-Loads,
     * Zusatzfilter).
     *
     * @param  Builder<TimeEntry>  $query
     * @return Builder<TimeEntry>
     */
    protected function scopeCandidates(Builder $query): Builder {
        return $query;
    }

    /**
     * payload der pushed-Reference (null = ohne).
     *
     * @return array<string, mixed>|null
     */
    protected function pushedPayload(TimeEntry $entry): ?array {
        return null;
    }

    /**
     * Bucht offene Zeiten der Organisation ins Fremdsystem zurück.
     *
     * @param  array<string, mixed>  $config
     * @return array{pushed: int, skipped: int, failed: int, errors: list<string>}
     */
    public function exportPending(Organization $organization, array $config, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null): array {
        $error = $this->prepareExport($organization, $config);
        if ($error !== null) {
            return ['pushed' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => [$error]];
        }

        $pushed = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];

        foreach ($this->candidates($organization, $from, $to) as $entry) {
            if ($this->alreadyPushed($organization, $entry) || $this->shouldSkip($organization, $entry)) {
                $skipped++;

                continue;
            }

            try {
                $externalId = $this->createRemoteEntry($organization, $entry);

                $this->recordPushed($organization, $entry, $externalId);
                $this->markPushed($entry);
                $pushed++;
            } catch (\Throwable $e) {
                if ($this->shouldAbort($e)) {
                    // Drosselung: Lauf abbrechen, der Rest bleibt offen für den nächsten Durchgang.
                    $errors[] = $e->getMessage();

                    break;
                }
                if (! $this->isExpectedFailure($e)) {
                    throw $e;
                }

                $errors[] = (string) __('Zeiteintrag #:id: :message', ['id' => $entry->id, 'message' => $e->getMessage()]);
                $failed++;
            }
        }

        return ['pushed' => $pushed, 'skipped' => $skipped, 'failed' => $failed, 'errors' => $errors];
    }

    /**
     * Nach erfolgreicher Remote-Anlage. Standard: als exportiert markieren
     * („Rückbuchung", Kimai/OpenProject) — der Eintrag verschwindet damit aus
     * der lokalen Abrechnung. Spiegel-Exporte (Toggl) überschreiben als No-op;
     * dann MUSS die Kandidaten-Query gepushte Einträge selbst ausschließen.
     */
    protected function markPushed(TimeEntry $entry): void {
        $entry->forceFill(['exported' => true])->save();
    }

    /**
     * Nicht-exportierte Zeiteinträge (> 0 Minuten) gemappter Projekte,
     * optional im Datumsfenster.
     *
     * @return Collection<int, TimeEntry>
     */
    protected function candidates(Organization $organization, ?CarbonImmutable $from, ?CarbonImmutable $to): Collection {
        $projectIds = $this->exportableProjectIds($organization);
        if ($projectIds === []) {
            return collect();
        }

        $fromDate = $from?->toDateString();
        $toDate = $to?->toDateString();

        $query = TimeEntry::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereIn('project_id', $projectIds)
            ->where('exported', false)
            ->where('minutes', '>', 0)
            ->when($fromDate !== null, fn($q) => $q->whereDate('date', '>=', $fromDate))
            ->when($toDate !== null, fn($q) => $q->whereDate('date', '<=', $toDate));

        return $this->scopeCandidates($query)->orderBy('date')->get();
    }

    protected function alreadyPushed(Organization $organization, TimeEntry $entry): bool {
        return ExternalReference::query()
            ->forPlugin($organization, $this->pluginId(), self::EXT_TYPE_PUSHED)
            ->forReferenceable($entry)
            ->exists();
    }

    protected function recordPushed(Organization $organization, TimeEntry $entry, string $externalId): void {
        ExternalReference::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'plugin_id' => $this->pluginId(),
                'external_type' => self::EXT_TYPE_PUSHED,
                'referenceable_type' => $entry->getMorphClass(),
                'referenceable_id' => $entry->getKey(),
            ],
            [
                'external_id' => $externalId,
                'payload' => $this->pushedPayload($entry),
                'synced_at' => now(),
            ],
        );
    }
}
