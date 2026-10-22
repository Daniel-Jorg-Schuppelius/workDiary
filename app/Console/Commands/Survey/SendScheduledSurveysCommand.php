<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SendScheduledSurveysCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Survey;

use App\Services\Survey\SurveyService;
use Illuminate\Console\Command;

/**
 * Umfragen im festen Abstand (MVP-1293): eingeladen werden Kunden mit
 * abgeschlossenem Auftrag oder gelöstem Ticket im Abstand, die diese Umfrage
 * im Abstand noch nicht bekommen haben. Ermüdungsschutz und Opt-out gelten.
 */
final class SendScheduledSurveysCommand extends Command {
    protected $signature = 'surveys:send-scheduled';

    protected $description = 'Lädt aktive Kunden zu Umfragen mit festem Abstand ein.';

    public function handle(SurveyService $surveys): int {
        $sent = $surveys->sendScheduled();
        $this->info(sprintf('%d Einladung(en) versandt.', $sent));

        return self::SUCCESS;
    }
}
