<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MaintenanceDueScanCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands\Asset;

use App\Models\MaintenancePlan;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class MaintenanceDueScanCommand extends Command {
    protected $signature = 'maintenance:scan-due {--lookahead=0 : Zusätzliche Tage Vorlauf, die ebenfalls als fällig markiert werden}';

    protected $description = 'Scannt aktive Wartungspläne auf Fälligkeit und schreibt einen Audit-Trail.';

    public function handle(): int {
        $lookahead = max(0, (int) $this->option('lookahead'));
        $reference = Carbon::now()->addDays($lookahead);

        $due = 0;
        MaintenancePlan::query()
            ->where('is_active', true)
            ->whereNotNull('next_due_on')
            ->where('next_due_on', '<=', $reference->toDateString())
            ->chunkById(200, function ($plans) use (&$due, $reference): void {
                foreach ($plans as $plan) {
                    if (! $plan->isDue($reference)) {
                        continue;
                    }
                    $plan->audit('maintenance_plan.due_detected', [
                        'next_due_on' => $plan->next_due_on?->toDateString(),
                        'reference' => $reference->toDateString(),
                    ]);
                    $due++;
                }
            });

        $this->info(sprintf('%d fällige Wartungsplan-Einträge erkannt (Referenz: %s).', $due, $reference->toDateString()));

        return self::SUCCESS;
    }
}
