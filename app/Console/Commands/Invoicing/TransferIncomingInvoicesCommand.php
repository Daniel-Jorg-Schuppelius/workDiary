<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TransferIncomingInvoicesCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Invoicing;

use App\Console\Concerns\IteratesOrganizations;
use App\Models\Platform\Organization;
use App\Services\Invoicing\EInvoice\IncomingInvoiceTransferService;
use Illuminate\Console\Command;

/**
 * Wiederholt offene, wartende und fehlgeschlagene Übergaben des
 * Rechnungseingangs (Feature 163, MVP-1111) und holt Eingänge nach, die vor
 * dem Einschalten eines Ziels zugeordnet wurden.
 */
class TransferIncomingInvoicesCommand extends Command {
    use IteratesOrganizations;

    protected $signature = 'incoming-invoices:transfer ' . self::ORGANIZATION_OPTION;

    protected $description = 'Übergibt zugeordnete Rechnungseingänge an die eingeschalteten Buchhaltungsziele.';

    public function handle(IncomingInvoiceTransferService $transfers): int {
        $this->forEachOrganization(function (Organization $org) use ($transfers): void {
            $counts = $transfers->retryOpen($org);
            if ($counts['transferred'] + $counts['failed'] > 0) {
                $this->line("Organisation #{$org->id}: {$counts['transferred']} übergeben, {$counts['failed']} fehlgeschlagen.");
            }
        });

        return self::SUCCESS;
    }
}
