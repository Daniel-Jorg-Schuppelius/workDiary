<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CloseStaleVisitsCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Location;

use App\Services\Location\VisitBuilder;
use Illuminate\Console\Command;

/**
 * Offene Besuche ohne weiteren Punkt seit `location.stale_visit_hours`
 * schließen (MVP-1186) — sonst entsteht nie ein Vorschlag, wenn das Gerät am
 * Kunden ausgeht.
 */
class CloseStaleVisitsCommand extends Command {
    protected $signature = 'location:close-stale-visits {--hours= : Stunden ohne Punkt (Standard aus config/location.php)}';

    protected $description = 'Schließt offene Standort-Besuche ohne neuen Punkt und erzeugt ihre Zeitvorschläge.';

    public function handle(VisitBuilder $builder): int {
        $hours = $this->option('hours') !== null ? (int) $this->option('hours') : (int) config('location.stale_visit_hours', 6);
        $closed = $builder->closeStaleVisits(max(1, $hours));
        $this->info(sprintf('%d verwaiste(r) Besuch(e) geschlossen.', $closed));

        return self::SUCCESS;
    }
}
