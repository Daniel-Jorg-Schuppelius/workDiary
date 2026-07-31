<?php
/*
 * Created on   : Fri Jul 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TogglRepairEntryBillableCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Toggl\Console;

use App\Console\Concerns\IteratesOrganizations;
use App\Models\{ExternalReference, InvoiceItem, Organization, TimeEntry};
use App\Plugins\Support\{MatchingTimeImportService, TimeWritebackObserver};
use App\Plugins\Toggl\{TogglConfig, TogglPlugin};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Setzt Toggl-importierte Zeiten mit hartem billable=false auf die effektive
 * Projekt-Abrechenbarkeit zurück: Toggl Free meldete nie ein echtes Signal
 * (das Flag ist dort Premium), der Import bis 2026-07 übernahm das false aber
 * wörtlich. Gegenstück auf Eintragsebene zur Projekt-Migration
 * 2026_11_06_100000_backfill_toggl_project_billable_to_inherit.
 *
 * Idempotent — kann nach dem Buchen alter Inbox-Gruppen (Legacy-Snapshots
 * buchen weiterhin false) gefahrlos erneut laufen. Ohne --apply nur Dry-Run.
 */
class TogglRepairEntryBillableCommand extends Command {
    use IteratesOrganizations;

    protected $signature = 'toggl:repair-entry-billable '
        . self::ORGANIZATION_OPTION
        . ' {--apply : Änderungen schreiben (sonst Dry-Run)}';

    protected $description = 'Setzt Toggl-importierte Zeiten mit billable=false auf die effektive Projekt-Abrechenbarkeit zurück (Free-Plan lieferte nie ein echtes Signal). Ohne --apply nur Dry-Run.';

    public function handle(): int {
        $apply = (bool) $this->option('apply');
        $organizations = $this->organizationsToProcess();
        if ($organizations->isEmpty()) {
            $this->warn('Keine Organisationen gefunden.');

            return self::SUCCESS;
        }

        foreach ($organizations as $org) {
            // default_billable=false ist eine bewusste Org-Entscheidung
            // („nie als abrechenbar") — dort nichts umdrehen.
            if (! TogglConfig::resolve($org->id)['default_billable']) {
                $this->warn("Organisation #{$org->id} ({$org->name}): default_billable ist aus — übersprungen.");

                continue;
            }

            // Wie beim Import: die Korrektur darf kein billable=true nach
            // Toggl zurückschreiben (billable ist writeback-gespiegelt).
            TimeWritebackObserver::suppressed(fn () => $this->repairOrganization($org, $apply));
        }

        return self::SUCCESS;
    }

    private function repairOrganization(Organization $org, bool $apply): void {
        $fixed = 0;
        $skippedExported = 0;
        $skippedInvoiced = 0;
        $skippedSigned = 0;
        $skippedNotBillable = 0;

        ExternalReference::query()
            ->forPlugin($org->id, TogglPlugin::ID, MatchingTimeImportService::EXT_TYPE_ENTRY)
            ->where('referenceable_type', (new TimeEntry)->getMorphClass())
            ->orderBy('id')
            ->chunkById(200, function ($refs) use ($org, $apply, &$fixed, &$skippedExported, &$skippedInvoiced, &$skippedSigned, &$skippedNotBillable): void {
                // Console-Kontext: kein currentOrganization-Binding → Scope
                // umgehen und die Organisation explizit filtern.
                $entries = TimeEntry::query()
                    ->withoutGlobalScopes()
                    ->where('organization_id', $org->id)
                    ->whereIn('id', $refs->pluck('referenceable_id'))
                    ->where('billable', false)
                    ->with(['project.parent', 'project.customer', 'timesheet'])
                    ->get();

                foreach ($entries as $entry) {
                    if (! ($entry->project?->effectiveBillable() ?? true)) {
                        $skippedNotBillable++;

                        continue;
                    }
                    if ($entry->exported) {
                        $skippedExported++;

                        continue;
                    }
                    if ($this->isInvoiceLinked($entry)) {
                        $skippedInvoiced++;

                        continue;
                    }
                    if ($entry->timesheet?->isSigned() === true) {
                        $skippedSigned++;

                        continue;
                    }

                    if ($apply) {
                        // Eloquent-Save: Boot rechnet den Satz-Snapshot
                        // (rate/internal_rate) neu, Auditable protokolliert.
                        $entry->billable = true;
                        $entry->save();
                    }
                    $fixed++;
                }
            });

        $mode = $apply ? 'umgesetzt' : 'würden umgesetzt (Dry-Run, --apply zum Schreiben)';
        $this->info("Organisation #{$org->id} ({$org->name}):");
        $this->line("  {$fixed} Einträge {$mode}, {$skippedExported} übersprungen (exportiert), "
            . "{$skippedInvoiced} übersprungen (abgerechnet), {$skippedSigned} übersprungen (signiert), "
            . "{$skippedNotBillable} übersprungen (Projekt nicht abrechenbar).");
    }

    /** Direkt-FK und Sammelpositionen-Pivot — beide zählen als abgerechnet. */
    private function isInvoiceLinked(TimeEntry $entry): bool {
        return InvoiceItem::query()->withoutGlobalScopes()->where('time_entry_id', $entry->getKey())->exists()
            || DB::table('invoice_item_time_entries')->where('time_entry_id', $entry->getKey())->exists();
    }
}
