<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InitValuationLayersCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Concerns\IteratesOrganizations;
use App\Services\Inventory\ValuationBackfillService;
use Illuminate\Console\Command;

/**
 * Initialisiert FIFO/FEFO-Zugangsschichten aus dem gleitenden Durchschnitt
 * (Feature 048, E3). Für die Umstellung bestehender Bestände auf schichtbasierte
 * Bewertung. Idempotent.
 */
class InitValuationLayersCommand extends Command {
    use IteratesOrganizations;

    protected $signature = 'inventory:init-valuation-layers {--org= : Organisations-ID (sonst alle)}';

    protected $description = 'Erzeugt initiale Bewertungsschichten aus dem gleitenden Durchschnitt (FIFO/FEFO-Umstellung)';

    public function handle(ValuationBackfillService $backfill): int {
        $organizations = $this->organizationsToProcess('org', fn ($q) => $q->withoutGlobalScopes());

        $total = 0;
        foreach ($organizations as $organization) {
            $count = $backfill->backfill($organization);
            $total += $count;
            $this->line(sprintf('Org #%d: %d Schicht(en) angelegt.', $organization->id, $count));
        }

        $this->info(sprintf('Fertig – %d Schicht(en) insgesamt.', $total));

        return self::SUCCESS;
    }
}
