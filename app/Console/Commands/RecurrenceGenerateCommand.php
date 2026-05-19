<?php

/*
 * Created on   : Tue May 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecurrenceGenerateCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands;

use App\Services\Recurrence\RecurrenceGenerator;
use Illuminate\Console\Command;

class RecurrenceGenerateCommand extends Command
{
    protected $signature = 'recurrence:generate {--lookahead=28 : Tage in die Zukunft, für die Aufträge erzeugt werden}';

    protected $description = 'Erzeugt aus aktiven Wiederkehr-Regeln fehlende Aufträge im definierten Vorausschau-Fenster.';

    public function handle(RecurrenceGenerator $generator): int
    {
        $lookahead = max(1, (int) $this->option('lookahead'));
        $created = $generator->generateAll($lookahead);

        $this->info(sprintf('%d Auftr%s erzeugt (Lookahead: %d Tage).', $created, $created === 1 ? 'ag' : 'äge', $lookahead));

        return self::SUCCESS;
    }
}
