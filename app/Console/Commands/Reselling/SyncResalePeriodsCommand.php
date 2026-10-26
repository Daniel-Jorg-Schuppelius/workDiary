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

use App\Models\Platform\Organization;
use App\Models\Reselling\ResaleSubscription;
use App\Services\Reselling\Register\{LinkProposer, PeriodPlanner, ResaleContractObligationSync};
use Illuminate\Console\Command;

/**
 * Rollt die Abrechnungsperioden aller planbaren Abos bis zum Planungshorizont
 * vor (Feature 152) und gleicht danach je Organisation die Abo-Termine mit
 * dem Vertragskalender ab (079, Abos mit Vertrag). Täglich im Zeitplan; idempotent.
 */
class SyncResalePeriodsCommand extends Command {
    protected $signature = 'resale:sync-periods {--org= : Nur diese Organisation (ID)}';

    protected $description = 'Abrechnungsperioden der Reselling-Abos bis zum Planungshorizont anlegen';

    public function handle(PeriodPlanner $planner, ResaleContractObligationSync $obligations, LinkProposer $proposer): int {
        // Auch abgelöste und beendete Verträge der letzten 36 Monate (Feature 185, MVP-1402): eine
        // spätere Abtretung ändert ihr Soll, sonst bleibt der alte Vertrag ewig „teilweise".
        $query = ResaleSubscription::query()->withoutGlobalScopes()->replannable();
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

        // Zweiter Schritt: Kündigungsfrist/Verlängerungswarnung je Abo mit Vertrag — aus den eben geplanten Perioden.
        $organizations = Organization::query()->orderBy('id');
        if ($this->option('org') !== null) {
            $organizations->where('id', (int) $this->option('org'));
        }
        $calendar = ['created' => 0, 'updated' => 0, 'closed' => 0];
        foreach ($organizations->get() as $organization) {
            foreach ($obligations->sync($organization) as $key => $value) {
                $calendar[$key] += $value;
            }
        }
        $this->info(sprintf('Vertragsobligationen: %d neu, %d aktualisiert, %d geschlossen.', $calendar['created'], $calendar['updated'], $calendar['closed']));
        // Dritter Schritt (Feature 185, MVP-1406): neue Perioden sofort gegen den Spiegel abgleichen.
        foreach ($organizations->get() as $organization) {
            try {
                $proposer->propose($organization);
            } catch (\RuntimeException $e) {
                $this->warn(sprintf('Organisation #%d: %s', $organization->id, $e->getMessage()));
            }
        }

        return self::SUCCESS;
    }
}
