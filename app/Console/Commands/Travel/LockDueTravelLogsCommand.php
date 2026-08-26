<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LockDueTravelLogsCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Travel;

use App\Services\Travel\TravelLogService;
use Illuminate\Console\Command;

/**
 * Tagesende-Festschreibung des steuerlichen Fahrtenbuchs (Feature 137,
 * MVP-702): Logbook-Fahrten vergangener Tage werden unveränderlich.
 */
class LockDueTravelLogsCommand extends Command {
    protected $signature = 'travel-logs:lock-due';

    protected $description = 'Schreibt Fahrtenbuch-Fahrten (Logbook-Modus) vergangener Tage fest.';

    public function handle(TravelLogService $service): int {
        $count = $service->lockDue();
        $this->info("{$count} Fahrt(en) festgeschrieben.");

        return self::SUCCESS;
    }
}
