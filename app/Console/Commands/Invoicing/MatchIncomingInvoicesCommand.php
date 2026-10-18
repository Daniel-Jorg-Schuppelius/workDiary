<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MatchIncomingInvoicesCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Invoicing;

use App\Console\Concerns\IteratesOrganizations;
use App\Models\Platform\Organization;
use App\Services\Invoicing\EInvoice\IncomingInvoiceMatcher;
use Illuminate\Console\Command;

/**
 * Ordnet Rechnungseingänge ohne Gegenpartei exakt nach (Feature 163, MVP-1108):
 * einmal für den Bestand nach dem Einspielen, danach jederzeit wiederholbar —
 * etwa nachdem Stammdaten eine USt-IdNr. oder IBAN bekommen haben.
 */
class MatchIncomingInvoicesCommand extends Command {
    use IteratesOrganizations;

    protected $signature = 'incoming-invoices:match ' . self::ORGANIZATION_OPTION;

    protected $description = 'Ordnet Rechnungseingänge ohne Lieferant bzw. Kunde über exakte Merkmale zu.';

    public function handle(IncomingInvoiceMatcher $matcher): int {
        foreach ($this->organizationsToProcess() as $org) {
            $this->withOrganizationContext($org, function (Organization $org) use ($matcher): void {
                $result = $matcher->rematchOpen((int) $org->id);
                $this->info(sprintf('Organisation #%d: %d zugeordnet, %d weiter offen.', $org->id, $result['assigned'], $result['open']));
            });
        }

        return self::SUCCESS;
    }
}
