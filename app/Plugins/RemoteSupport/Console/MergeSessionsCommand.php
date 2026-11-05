<?php
/*
 * Created on   : Thu Nov 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MergeSessionsCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\RemoteSupport\Console;

use App\Console\Concerns\IteratesOrganizations;
use App\Models\Asset\Asset;
use App\Models\Integration\{ExternalReference, ExternalReferenceAlias};
use App\Models\Platform\Organization;
use App\Models\Project\Project;
use App\Models\Time\TimeEntry;
use App\Plugins\RemoteSupport\Models\RemotePendingSession;
use App\Plugins\RemoteSupport\Providers\RemoteSession;
use App\Plugins\RemoteSupport\{RemoteSupportConfig, RemoteSupportPlugin};
use App\Plugins\RemoteSupport\Services\RemoteSessionImporter;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Nachlauf für den Bestand: verkettet Fernwartungs-Einträge nach derselben
 * Regel wie der Import ({@see RemoteSessionImporter::absorbSession()}) —
 * eine Folgeverbindung desselben Benutzers, Kunden und Geräts, die höchstens
 * `merge_gap_minutes` nach dem Ende des Kopfeintrags begann, verlängert ihn
 * und geht in ihm auf; Einträge unter einer Minute werden auf eine Minute
 * gesetzt. Exportierte Einträge und abgeschlossene Monate bleiben unberührt.
 */
class MergeSessionsCommand extends Command {
    use IteratesOrganizations;

    protected $signature = 'remote:merge-sessions ' . self::ORGANIZATION_OPTION . '
        {--days=90 : Zeitraum rückwirkend in Tagen}
        {--dry-run : Nur anzeigen, was zusammengeführt würde}';

    protected $description = 'Bestehende Fernwartungs-Einträge verketten: Folgeverbindungen desselben Geräts innerhalb der Toleranz verlängern den ersten Eintrag; Einträge unter einer Minute werden auf eine Minute gesetzt.';

    public function handle(RemoteSessionImporter $importer): int {
        $days = max(1, (int) $this->option('days'));
        $dryRun = (bool) $this->option('dry-run');

        $failures = $this->forEachOrganization(function (Organization $organization) use ($importer, $days, $dryRun): void {
            $config = RemoteSupportConfig::resolve((int) $organization->id);
            $gapMinutes = RemoteSessionImporter::mergeGapMinutes($config);
            $since = CarbonImmutable::now()->subDays($days)->toDateString();

            $references = ExternalReference::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->where('plugin_id', RemoteSupportPlugin::ID)
                ->where('external_type', RemoteSessionImporter::EXT_TYPE_SESSION)
                ->where('referenceable_type', (new TimeEntry)->getMorphClass())
                ->get()
                ->keyBy('referenceable_id');

            $entries = TimeEntry::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->whereIn('id', $references->keys()->all())
                ->where('date', '>=', $since)
                ->whereNotNull('started_at')
                ->whereNotNull('ended_at')
                ->orderBy('started_at')
                ->orderBy('id')
                ->get();

            $customerByProject = Project::query()
                ->withoutGlobalScopes()
                ->whereIn('id', $entries->pluck('project_id')->unique()->all())
                ->pluck('customer_id', 'id');

            // Kette = gleicher Benutzer, Kunde (über das Projekt) und Gerät.
            $groups = $entries->groupBy(static function (TimeEntry $entry) use ($references, $customerByProject): string {
                $assetId = (int) ($references[$entry->id]->payload['asset_id'] ?? 0);

                return $entry->user_id . '|' . ($customerByProject[$entry->project_id] ?? 'p' . $entry->project_id) . '|' . $assetId;
            });

            $chains = 0;
            $merged = 0;
            $lifted = 0;
            $skipped = 0;

            foreach ($groups as $group) {
                $result = $this->mergeGroup($organization, $importer, $references, $group, $gapMinutes, $dryRun);
                $chains += $result['chains'];
                $merged += $result['merged'];
                $skipped += $result['skipped'];
                $lifted += $this->liftShortEntries($organization, $importer, $result['remaining'], $dryRun);
            }

            $this->info(sprintf(
                '%sOrganisation #%d (%s): %d Ketten, %d Einträge zusammengeführt, %d auf eine Minute gesetzt, %d übersprungen (exportiert, Monat abgeschlossen oder Gerät fehlt).',
                $dryRun ? '[dry-run] ' : '',
                $organization->id,
                $organization->name,
                $chains,
                $merged,
                $lifted,
                $skipped,
            ));
        });

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Verkettet die chronologisch sortierten Einträge einer Gruppe.
     *
     * @param  Collection<int|string, ExternalReference>  $references
     * @param  Collection<int, TimeEntry>  $group
     * @return array{chains: int, merged: int, skipped: int, remaining: Collection<int, TimeEntry>}
     */
    private function mergeGroup(Organization $organization, RemoteSessionImporter $importer, Collection $references, Collection $group, int $gapMinutes, bool $dryRun): array {
        $chains = 0;
        $merged = 0;
        $skipped = 0;
        $remaining = collect();

        /** @var ?TimeEntry $head */
        $head = null;
        $headEnd = null;
        $headMerged = 0;

        foreach ($group as $entry) {
            if ($entry->started_at === null || $entry->ended_at === null) {
                continue;
            }
            $startedAt = CarbonImmutable::instance($entry->started_at);
            $endedAt = CarbonImmutable::instance($entry->ended_at);

            $continues = $head !== null
                && $headEnd !== null
                && $gapMinutes > 0
                && $startedAt <= $headEnd->addMinutes($gapMinutes)
                && $this->sameJob($head, $entry);

            if ($continues) {
                $reference = $references->get($entry->id);
                $asset = $reference instanceof ExternalReference
                    ? Asset::query()->withoutGlobalScopes()->find((int) ($reference->payload['asset_id'] ?? 0))
                    : null;

                if ($reference instanceof ExternalReference
                    && $asset instanceof Asset
                    && $importer->canExtend($organization, $head, $endedAt)
                    && $importer->canExtend($organization, $entry, $endedAt)) {
                    $this->line(sprintf(
                        '%s#%d ← #%d (%s – %s, %s)',
                        $dryRun ? '[dry-run] ' : '',
                        $head->id,
                        $entry->id,
                        $startedAt->format('Y-m-d H:i:s'),
                        $endedAt->format('H:i:s'),
                        $entry->description,
                    ));

                    if (! $dryRun) {
                        $this->absorb($organization, $importer, $head, $entry, $reference, $asset);
                        $head = $head->fresh() ?? $head;
                    }

                    $merged++;
                    $headMerged++;
                    $headEnd = $endedAt > $headEnd ? $endedAt : $headEnd;

                    continue;
                }

                // Gehört zur Kette, darf aber nicht aufgehen: bleibt eigener
                // Eintrag und wird Kopf der nächsten.
                $skipped++;
            }

            if ($headMerged > 0) {
                $chains++;
            }

            $remaining->push($entry);
            $head = $entry;
            $headEnd = $endedAt;
            $headMerged = 0;
        }

        if ($headMerged > 0) {
            $chains++;
        }

        return ['chains' => $chains, 'merged' => $merged, 'skipped' => $skipped, 'remaining' => $remaining];
    }

    /**
     * Gleiches Projekt, oder der Folgeeintrag trägt die Standardbeschreibung
     * ohne Notiz — eine Notiz, die im Import ein anderes Projekt ergab, bleibt
     * ein eigener Vorgang (Regel des Imports).
     */
    private function sameJob(TimeEntry $head, TimeEntry $entry): bool {
        if ((int) $head->project_id === (int) $entry->project_id) {
            return true;
        }

        return str_ends_with((string) $entry->description, '(' . __('Fernwartungssitzung') . ')');
    }

    /** Lässt den Folgeeintrag im Kopf aufgehen und entfernt ihn. */
    private function absorb(Organization $organization, RemoteSessionImporter $importer, TimeEntry $head, TimeEntry $entry, ExternalReference $reference, Asset $asset): void {
        if ($entry->started_at === null || $entry->ended_at === null) {
            return;
        }

        [$provider, $sessionId] = array_pad(explode(':', (string) $reference->external_id, 2), 2, '');
        $provider = (string) ($reference->payload['provider'] ?? $provider);

        $session = new RemoteSession(
            provider: $provider,
            sessionId: $sessionId,
            remoteId: (string) ($reference->payload['remote_id'] ?? ''),
            startedAt: CarbonImmutable::instance($entry->started_at),
            endedAt: CarbonImmutable::instance($entry->ended_at),
            note: $this->noteOf($entry, $asset),
        );

        // Die Primärreferenz des Folgeeintrags wird zum Alias am Kopf
        // (absorbSession legt ihn an, weil der Kopf seine Primärreferenz hat).
        $reference->delete();
        $importer->absorbSession($organization, $head, $session, $asset);

        ExternalReferenceAlias::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', RemoteSupportPlugin::ID)
            ->where('referenceable_type', $entry->getMorphClass())
            ->where('referenceable_id', $entry->getKey())
            ->update(['referenceable_id' => $head->getKey()]);

        RemotePendingSession::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('time_entry_id', $entry->id)
            ->update(['time_entry_id' => $head->id]);

        $entry->tags()->detach();
        $entry->delete();
    }

    /** Notiz aus „Gerät (Notiz)“; der Platzhalter ohne Notiz ergibt null. */
    private function noteOf(TimeEntry $entry, Asset $asset): ?string {
        $description = trim((string) $entry->description);
        $prefix = (string) ($asset->name ?: $asset->asset_no);
        if ($prefix !== '' && str_starts_with($description, $prefix)) {
            $description = trim(substr($description, strlen($prefix)));
        }
        if (str_starts_with($description, '(') && str_ends_with($description, ')')) {
            $description = substr($description, 1, -1);
        }
        $description = trim($description);

        return $description === '' || $description === (string) __('Fernwartungssitzung') ? null : $description;
    }

    /**
     * Verbleibende Einträge unter einer Minute auf eine Minute heben
     * ({@see RemoteSessionImporter::minimumEnd()}).
     *
     * @param  Collection<int, TimeEntry>  $entries
     */
    private function liftShortEntries(Organization $organization, RemoteSessionImporter $importer, Collection $entries, bool $dryRun): int {
        $lifted = 0;
        foreach ($entries as $entry) {
            $fresh = $entry->fresh();
            if ($fresh === null || $fresh->started_at === null || $fresh->ended_at === null) {
                continue;
            }
            $startedAt = CarbonImmutable::instance($fresh->started_at);
            $endedAt = CarbonImmutable::instance($fresh->ended_at);
            $floor = RemoteSessionImporter::minimumEnd($startedAt, $endedAt);
            if ($floor->equalTo($endedAt) || ! $importer->canExtend($organization, $fresh, $floor)) {
                continue;
            }

            $this->line(sprintf('%s#%d auf eine Minute (%s)', $dryRun ? '[dry-run] ' : '', $fresh->id, $fresh->description));
            if (! $dryRun) {
                $fresh->ended_at = \Illuminate\Support\Carbon::instance($floor);
                $fresh->save();
            }
            $lifted++;
        }

        return $lifted;
    }
}
