<?php
/*
 * Created on   : Thu Jun 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ImportMinimumWagesCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands;

use App\Services\Payroll\EurostatMinimumWageImporter;
use Illuminate\Console\Command;
use Throwable;

/**
 * Importiert die monatlichen gesetzlichen Mindestlöhne aller gemeldeten Länder
 * von Eurostat (earn_mw_avgr2) in die Referenztabelle. Läuft manuell sowie
 * geplant (siehe routes/console.php) und per Button auf der Lohn-Seite.
 */
class ImportMinimumWagesCommand extends Command {
    protected $signature = 'payroll:import-minimum-wages';

    protected $description = 'Importiert die EU-Mindestlöhne (Eurostat earn_mw_avgr2) in die Referenztabelle.';

    public function handle(EurostatMinimumWageImporter $importer): int {
        try {
            $count = $importer->import();
        } catch (Throwable $e) {
            $this->error('Import fehlgeschlagen: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info("Eurostat-Mindestlöhne importiert: {$count} Datenpunkte.");

        return self::SUCCESS;
    }
}
