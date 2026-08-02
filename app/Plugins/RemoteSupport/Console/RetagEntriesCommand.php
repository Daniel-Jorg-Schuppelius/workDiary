<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RetagEntriesCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\RemoteSupport\Console;

use App\Console\Concerns\IteratesOrganizations;
use App\Models\{ExternalReference, Organization, TimeEntry};
use App\Plugins\RemoteSupport\{RemoteSupportPlugin, RemoteSupportService};
use Illuminate\Console\Command;

/**
 * Einmaliger Backfill für Fernwartungs-Zeiteinträge: entfernt das historische
 * Provider-Präfix („Anydesk — …"/„Teamviewer — …") aus der Beschreibung und
 * zieht stattdessen die Tags (Anbieter + Remote) nach — Anker ist die
 * session-ExternalReference des Plugins, nicht der Beschreibungstext.
 */
class RetagEntriesCommand extends Command {
    use IteratesOrganizations;

    protected $signature = 'remote:retag-entries ' . self::ORGANIZATION_OPTION . '
        {--dry-run : Nur anzeigen, was geändert würde}';

    protected $description = 'Bestehende Fernwartungs-Einträge: Provider-Präfix aus der Beschreibung entfernen und Tags (Anbieter + Remote) nachziehen.';

    public function handle(RemoteSupportService $service): int {
        $dryRun = (bool) $this->option('dry-run');

        $failures = $this->forEachOrganization(function (Organization $organization) use ($service, $dryRun): void {
            $tagged = 0;
            $stripped = 0;
            $skippedExported = 0;

            ExternalReference::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->where('plugin_id', RemoteSupportPlugin::ID)
                ->where('external_type', RemoteSupportService::EXT_TYPE_SESSION)
                ->where('referenceable_type', (new TimeEntry)->getMorphClass())
                ->chunkById(200, function ($references) use ($service, $organization, $dryRun, &$tagged, &$stripped, &$skippedExported): void {
                    foreach ($references as $reference) {
                        $entry = TimeEntry::query()->withoutGlobalScopes()->find($reference->referenceable_id);
                        if ($entry === null) {
                            continue;
                        }

                        // Provider steckt im Payload; Fallback ist der
                        // sessionKey („provider:sessionId") der Referenz.
                        $provider = (string) ($reference->payload['provider'] ?? explode(':', (string) $reference->external_id, 2)[0]);

                        if (! $dryRun) {
                            $service->applyRemoteTags($organization, $entry, $provider);
                        }
                        $tagged++;

                        // Nur echte Import-Beschreibungen strippen — verknüpfte
                        // (fremd erfasste) Einträge tragen das Präfix nicht.
                        $description = (string) $entry->description;
                        $cleaned = preg_replace('/^(?:anydesk|teamviewer)\s*—\s*/i', '', $description);
                        if ($cleaned === null || $cleaned === $description) {
                            continue;
                        }

                        // Exportierte Einträge spiegeln externe Systeme —
                        // deren Beschreibung bleibt unangetastet.
                        if ((bool) $entry->exported) {
                            $skippedExported++;

                            continue;
                        }

                        if (! $dryRun) {
                            $entry->description = $cleaned;
                            $entry->save();
                        }
                        $stripped++;
                    }
                });

            $prefix = $dryRun ? '[dry-run] ' : '';
            $this->info(sprintf(
                '%sOrganisation #%d (%s): %d Einträge getaggt, %d Beschreibungen bereinigt, %d exportierte übersprungen.',
                $prefix,
                $organization->id,
                $organization->name,
                $tagged,
                $stripped,
                $skippedExported,
            ));
        });

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }
}
