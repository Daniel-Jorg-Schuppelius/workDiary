<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SyncResalePeriodsCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Reselling;

use App\Models\Reselling\ResaleSubscription;
use App\Services\Reselling\Register\PeriodPlanner;
use Illuminate\Console\Command;

/**
 * Rollt die Abrechnungsperioden aller planbaren Abos bis zum Planungshorizont
 * vor (Feature 152). Täglich im Zeitplan; idempotent.
 */
class SyncResalePeriodsCommand extends Command {
    protected $signature = 'resale:sync-periods {--org= : Nur diese Organisation (ID)}';

    protected $description = 'Abrechnungsperioden der Reselling-Abos bis zum Planungshorizont anlegen';

    public function handle(PeriodPlanner $planner): int {
        $query = ResaleSubscription::query()->withoutGlobalScopes()->planning();
        if ($this->option('org') !== null) {
            $query->where('organization_id', (int) $this->option('org'));
        }

        $totals = ['created' => 0, 'updated' => 0, 'removed' => 0, 'kept' => 0];
        $count = 0;
        $query->orderBy('id')->chunkById(200, function ($subscriptions) use ($planner, &$totals, &$count): void {
            foreach ($subscriptions as $subscription) {
                $result = $planner->sync($subscription);
                foreach ($result as $key => $value) {
                    $totals[$key] += $value;
                }
                $count++;
            }
        });

        $this->info(sprintf('%d Abos geplant: %d neu, %d aktualisiert, %d entfernt, %d unverändert.', $count, $totals['created'], $totals['updated'], $totals['removed'], $totals['kept']));

        return self::SUCCESS;
    }
}
