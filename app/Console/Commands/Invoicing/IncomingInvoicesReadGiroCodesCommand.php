<?php
/*
 * Created on   : Wed Nov 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingInvoicesReadGiroCodesCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Invoicing;

use App\Console\Concerns\IteratesOrganizations;
use App\Models\Invoicing\IncomingEInvoice;
use App\Models\Platform\Organization;
use App\Services\Invoicing\GiroCode\GiroCodeReader;
use App\Services\Invoicing\Split\IncomingInvoiceSplitter;
use Illuminate\Console\Command;

/**
 * Rechnungseingang (MVP-1807): liest GiroCodes nach, deren Lesung beim Eingang
 * am fehlenden `zbarimg` scheiterte — der Stand steht am Beleg und heilt nicht
 * von selbst. Nur offene, nicht übergebene Eingänge; der Deploy ruft den
 * Befehl nach der Werkzeuginstallation auf.
 */
class IncomingInvoicesReadGiroCodesCommand extends Command {
    use IteratesOrganizations;

    protected $signature = 'incoming-invoices:read-girocodes ' . self::ORGANIZATION_OPTION;

    protected $description = 'Liest GiroCodes im Rechnungseingang nach, deren Lesung am fehlenden Lesewerkzeug (zbarimg) scheiterte.';

    public function handle(GiroCodeReader $reader): int {
        if (! $reader->isAvailable()) {
            $this->warn('zbarimg fehlt — nichts nachzulesen (Paket zbar-tools, config/executables.json).');

            return self::SUCCESS;
        }
        $failures = $this->forEachOrganization(function (Organization $org) use ($reader): void {
            $read = 0;
            // `girocode` ist verschlüsselt: der Grund lässt sich nicht in SQL filtern.
            IncomingEInvoice::query()->openForWork()->whereNull('transferred_at')->whereNotNull('girocode')
                ->chunkById(200, function ($incomings) use ($reader, &$read): void {
                    foreach ($incomings as $incoming) {
                        if (GiroCodeReader::lacksTool($incoming) && ! IncomingInvoiceSplitter::isHolding($incoming) && $reader->apply($incoming) !== null) {
                            $read++;
                        }
                    }
                });
            if ($read > 0) {
                $this->line("Organisation #{$org->id}: {$read} GiroCodes nachgelesen.");
            }
        });

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
