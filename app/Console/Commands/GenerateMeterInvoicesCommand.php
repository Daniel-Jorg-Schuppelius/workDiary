<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GenerateMeterInvoicesCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Metering\MeterBillingService;
use Illuminate\Console\Command;

/**
 * Zählerstands-Faktura (Feature 116, MVP-605): fällige Vereinbarungen in
 * Rechnungs-ENTWÜRFE überführen — nie Auto-Ausstellung, nie Auto-Versand.
 * Übersprungene Läufe (fehlende Ablesung) sind ein Ergebnis, kein Fehler:
 * Sie bleiben als Zeile mit Grund stehen.
 */
class GenerateMeterInvoicesCommand extends Command {
    protected $signature = 'metering:generate-invoices';

    protected $description = 'Erzeugt Rechnungsentwürfe aus fälligen Zählerstands-Vereinbarungen.';

    public function handle(MeterBillingService $service): int {
        $result = $service->generateDue();

        $this->info(sprintf(
            'Entwürfe: %d, übersprungen: %d, extern geführt: %d',
            $result['created'],
            $result['skipped'],
            $result['blocked'],
        ));

        return self::SUCCESS;
    }
}
