<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SendDeadlineReminders.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Privacy;

use App\Services\Privacy\PrivacyDeadlineService;
use Illuminate\Console\Command;

/**
 * Erinnert an fristnahe/ueberfaellige Betroffenenanfragen (idempotent). Fuer den
 * Scheduler (stuendlich/taeglich) gedacht.
 */
class SendDeadlineReminders extends Command {
    protected $signature = 'privacy:deadlines';

    protected $description = 'Erinnert an fristnahe oder ueberfaellige Betroffenenanfragen (Art. 12).';

    public function handle(PrivacyDeadlineService $service): int {
        $count = $service->remind();
        $this->info("{$count} Anfrage(n) erinnert.");

        return self::SUCCESS;
    }
}
