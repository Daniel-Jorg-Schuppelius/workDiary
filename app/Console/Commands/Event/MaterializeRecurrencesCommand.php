<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MaterializeRecurrencesCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands\Event;

use App\Models\Event;
use App\Services\Event\RecurrenceService;
use Illuminate\Console\Command;

class MaterializeRecurrencesCommand extends Command {
    protected $signature = 'events:materialize-recurrences';

    protected $description = 'Materialisiert wiederkehrende Events innerhalb des konfigurierten Vorlaufzeitraums.';

    public function handle(RecurrenceService $service): int {
        $count = 0;
        Event::query()
            ->whereNotNull('recurrence_rule')
            ->whereNull('series_id')
            ->chunkById(100, function ($events) use ($service, &$count): void {
                foreach ($events as $event) {
                    $count += $service->materialize($event);
                }
            });

        $this->info("Materialized {$count} occurrence(s).");

        return self::SUCCESS;
    }
}
