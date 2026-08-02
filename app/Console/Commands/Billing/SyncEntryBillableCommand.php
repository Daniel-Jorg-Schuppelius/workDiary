<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SyncEntryBillableCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands\Billing;

use App\Console\Concerns\IteratesOrganizations;
use App\Models\{Customer, Organization, Project};
use App\Services\Billing\TimeEntryBillableSyncService;
use Illuminate\Console\Command;

/**
 * Einmal-Reparatur für den Abrechenbar-Durchgriff: Kunden/Projekte, die
 * bereits VOR dem Controller-Hook auf „nicht abrechenbar" gestellt wurden,
 * haben offene Zeiteinträge mit veraltetem billable=true-Snapshot.
 *
 * Bewusst nur die Nicht-abrechenbar-Richtung: es werden ausschließlich
 * explizit nicht abrechenbare Kunden/Projekte abgeglichen. Ein globaler
 * Abgleich in beide Richtungen würde gewollte Einzelfall-Kulanz
 * (billable=false unter abrechenbarem Kunden) plattziehen.
 *
 * Idempotent; Guards je Eintrag im Service (exportiert/rechnungsverknüpft/
 * signiert bleiben unberührt). Ohne --apply nur Dry-Run.
 */
class SyncEntryBillableCommand extends Command {
    protected $signature = 'billing:sync-entry-billable '
        . self::ORGANIZATION_OPTION
        . ' {--apply : Änderungen schreiben (sonst Dry-Run)}';

    protected $description = 'Gleicht offene Zeiteinträge nicht abrechenbarer Kunden/Projekte an deren Abrechenbarkeit an. Ohne --apply nur Dry-Run.';

    use IteratesOrganizations;

    public function __construct(private readonly TimeEntryBillableSyncService $sync) {
        parent::__construct();
    }

    public function handle(): int {
        $apply = (bool) $this->option('apply');
        $organizations = $this->organizationsToProcess();
        if ($organizations->isEmpty()) {
            $this->warn('Keine Organisationen gefunden.');

            return self::SUCCESS;
        }

        foreach ($organizations as $org) {
            $this->withOrganizationContext($org, function (Organization $org) use ($apply): void {
                $total = 0;

                foreach (Customer::query()->where('billable', false)->orderBy('id')->get() as $customer) {
                    $count = $this->sync->syncCustomer($customer, $apply);
                    if ($count > 0) {
                        $this->line('  Kunde "' . $customer->name . '": ' . $count . ' Einträge');
                        $total += $count;
                    }
                }

                foreach (Project::query()->where('billable', false)->orderBy('id')->get() as $project) {
                    $count = $this->sync->syncProject($project, $apply);
                    if ($count > 0) {
                        $this->line('  Projekt "' . $project->name . '": ' . $count . ' Einträge');
                        $total += $count;
                    }
                }

                $mode = $apply ? 'angepasst' : 'würden angepasst (Dry-Run, --apply zum Schreiben)';
                $this->info("Organisation #{$org->id} ({$org->name}): {$total} Einträge {$mode}.");
            });
        }

        return self::SUCCESS;
    }
}
