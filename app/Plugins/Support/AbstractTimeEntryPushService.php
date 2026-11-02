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

use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\Integration\ExternalReference;
use App\Models\Platform\Organization;
use App\Models\Time\TimeEntry;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\{Lock, LockTimeoutException};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Gemeinsames Skelett der Zeit-Export-Rückkanäle (OpenProject, Kimai, …):
 * Kandidaten-Query über gemappte Projekte, Idempotenz über die
 * `pushed_entry`-Reference, exportPending-Ergebnisschleife als
 * Template-Methode. Plugin-spezifisch bleiben Config-Validierung
 * ({@see prepareExport()}), Remote-Anlage ({@see createRemoteEntry()}),
 * Skip-Zusatzregeln ({@see shouldSkip()}) und die Abbruch-Bedingung
 * ({@see shouldAbort()}, z. B. Rate-Limit).
 *
 * Prüfung, Anlage und Referenz laufen je Eintrag unter einer Sperre
 * ({@see pushOne()}): Stunden-Batch und Outbox-Anlage starten zur vollen
 * Stunde gleichzeitig und sahen beide noch keine Referenz, bevor der jeweils
 * andere gepostet hatte (Befund 2026-11-02: doppelte Toggl-Einträge).
 */
abstract class AbstractTimeEntryPushService {
    public const EXT_TYPE_PUSHED = 'pushed_entry';

    /** Haltedauer der Sperre je Eintrag: Drossel, POST-Timeout und Verbindungs-Retries des Toolkits. */
    public const LOCK_SECONDS = 120;

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

    /**
     * Spiegel-Export (Toggl, Clockify) statt Rückbuchung: der Eintrag bleibt
     * lokal abrechenbar (`exported` bleibt false), dafür schließt JEDE
     * bestehende Referenz des Plugins (importiert oder gepusht) ihn als
     * Kandidaten aus — sonst liefe er dauerhaft als „skipped" durch jeden Lauf.
     * Kandidaten sind nur Work-Einträge mit echten Start-/Endzeiten (kon-19).
     */
    protected function mirrorsEntries(): bool {
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
            try {
                if ($this->pushOne($organization, $entry)) {
                    $pushed++;
                } else {
                    $skipped++;
                }
            } catch (LockTimeoutException) {
                // Ein anderer Lauf (Outbox-Anlage) überträgt den Eintrag gerade
                // und schreibt die Referenz — der nächste Batch prüft nach.
                $skipped++;
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
     * Einzel-Push für den Outbox-Create-Pfad (MVP-463): dieselben Schutzlinien
     * wie {@see exportPending()} — Konfiguration, Kandidaten-Query inkl.
     * plugin-eigener Referenz-Ausschlüsse ({@see scopeCandidates()}),
     * alreadyPushed, shouldSkip — für genau einen Eintrag. false = nicht
     * geeignet/nicht konfiguriert (Drop); API-Fehler laufen als Exception
     * nach oben (Outbox-Retry-Semantik).
     *
     * @param  array<string, mixed>  $config
     *
     * @throws LockTimeoutException der Stunden-Batch überträgt den Eintrag gerade — die Outbox wiederholt nach dem Backoff und findet dann dessen Referenz
     * @throws \GuzzleHttp\Exception\ConnectException|\Illuminate\Http\Client\ConnectionException Transportfehler des Clients — der Outbox-Dispatcher protokolliert sie ohne Retry
     */
    public function pushSingle(Organization $organization, array $config, TimeEntry $entry): bool {
        if ($this->prepareExport($organization, $config) !== null) {
            return false;
        }

        return $this->pushOne($organization, $entry);
    }

    /**
     * Eignung prüfen, anlegen und Referenz schreiben — unter der Sperre des
     * Eintrags, damit nur ein Prozess postet. Wer sie nicht bekommt, lässt den
     * Eintrag dem Halter. false = nicht geeignet (Drop).
     *
     * @throws LockTimeoutException
     * @throws \GuzzleHttp\Exception\ConnectException|\Illuminate\Http\Client\ConnectionException aus `createRemoteEntry()`
     */
    private function pushOne(Organization $organization, TimeEntry $entry): bool {
        $lock = self::lockFor($this->pluginId(), $organization, $entry);
        if (! $lock->get()) {
            throw new LockTimeoutException('Zeiteintrag #' . $entry->getKey() . ' wird gerade von einem anderen Lauf übertragen.');
        }

        try {
            if (! $this->isEligible($organization, $entry) || $this->alreadyPushed($organization, $entry) || $this->shouldSkip($organization, $entry)) {
                return false;
            }

            $externalId = $this->createRemoteEntry($organization, $entry);
            $this->recordPushed($organization, $entry, $externalId);
            $this->markPushed($entry);

            return true;
        } finally {
            $lock->release();
        }
    }

    /** Sperre je Plugin und Eintrag — Stunden-Batch und Outbox-Anlage teilen sie. */
    public static function lockFor(string $pluginId, Organization $organization, TimeEntry $entry): Lock {
        return Cache::lock('time-push:' . $pluginId . ':' . $organization->id . ':' . $entry->getKey(), self::LOCK_SECONDS);
    }

    /**
     * Kandidaten-Regeln für genau einen Eintrag: Projekt-Mapping, exported,
     * Minuten und die plugin-eigenen Referenz-Ausschlüsse aus
     * {@see scopeCandidates()} — unter der Sperre erneut, nicht nur in der
     * Kandidaten-Query des Laufs.
     */
    private function isEligible(Organization $organization, TimeEntry $entry): bool {
        $projectIds = $this->exportableProjectIds($organization);

        return $this->constrain(
            TimeEntry::query()
                ->withoutGlobalScopes()
                ->whereKey($entry->getKey())
                ->where('organization_id', $organization->id)
                ->whereIn('project_id', $projectIds === [] ? [0] : $projectIds)
                ->where('exported', false)
                ->where('minutes', '>', 0)
        )->exists();
    }

    /**
     * Gemeinsame Kandidaten-Regeln des Spiegel-Exports vor der
     * plugin-spezifischen Erweiterung ({@see scopeCandidates()}).
     *
     * @param  Builder<TimeEntry>  $query
     * @return Builder<TimeEntry>
     */
    private function constrain(Builder $query): Builder {
        if ($this->mirrorsEntries()) {
            $query
                ->whereNotNull('started_at')
                ->whereNotNull('ended_at')
                ->where('kind', TimeEntryKind::Work->value)
                ->whereNotExists(function ($sub): void {
                    $sub->from('external_references')
                        ->whereColumn('external_references.referenceable_id', 'time_entries.id')
                        ->where('external_references.referenceable_type', (new TimeEntry)->getMorphClass())
                        ->where('external_references.plugin_id', $this->pluginId())
                        ->whereIn('external_references.external_type', [
                            MatchingTimeImportService::EXT_TYPE_ENTRY,
                            self::EXT_TYPE_PUSHED,
                        ]);
                });
        }

        return $this->scopeCandidates($query);
    }

    /**
     * Nach erfolgreicher Remote-Anlage. Standard: als exportiert markieren
     * („Rückbuchung", Kimai/OpenProject) — der Eintrag verschwindet damit aus
     * der lokalen Abrechnung. Spiegel-Exporte ({@see mirrorsEntries()}) lassen
     * ihn abrechenbar; ihre Kandidaten-Query schließt Gepushtes selbst aus.
     */
    protected function markPushed(TimeEntry $entry): void {
        if ($this->mirrorsEntries()) {
            return;
        }

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

        return $this->constrain($query)->orderBy('date')->get();
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
