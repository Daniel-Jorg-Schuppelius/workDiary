<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CycleCountsCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Concerns\IteratesOrganizations;
use App\Models\{Organization, Warehouse};
use App\Services\Inventory\{CycleCountPlanner, StocktakeService};
use Illuminate\Console\Command;

/**
 * Terminierte zyklische Inventur (Feature 048, E6). Eröffnet je Lager eine
 * Zählung der fälligen ABC-Klasse (A häufig, C selten). Für den Scheduler
 * gedacht – z. B. A wöchentlich, B monatlich, C quartalsweise.
 */
class CycleCountsCommand extends Command {
    use IteratesOrganizations;

    protected $signature = 'inventory:cycle-counts {--class=A : ABC-Klasse (A/B/C)} {--org= : Organisations-ID (sonst alle)}';

    protected $description = 'Eröffnet zyklische Inventuren je Lager für die fällige ABC-Klasse';

    public function handle(CycleCountPlanner $planner, StocktakeService $stocktake): int {
        $class = strtoupper((string) $this->option('class'));

        $opened = 0;
        foreach ($this->organizationsToProcess('org') as $organization) {
            $this->withOrganizationContext($organization, function (Organization $organization) use ($planner, $stocktake, $class, &$opened): void {
                foreach (Warehouse::query()->where('organization_id', $organization->id)->get() as $warehouse) {
                    $variantIds = $planner->dueVariants($warehouse, [$class]);
                    if ($variantIds === []) {
                        continue;
                    }
                    $stocktake->openCycle($warehouse, $variantIds);
                    $opened++;
                    $this->line(sprintf('Org #%d / %s: Zyklus %s (%d Varianten).', $organization->id, $warehouse->name, $class, count($variantIds)));
                }
            });
        }

        $this->info(sprintf('Fertig – %d Zählung(en) eröffnet.', $opened));

        return self::SUCCESS;
    }
}
