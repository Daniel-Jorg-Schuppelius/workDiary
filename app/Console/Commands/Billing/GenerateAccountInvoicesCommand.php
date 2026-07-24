<?php
/*
 * Created on   : Thu Jul 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GenerateAccountInvoicesCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands\Billing;

use App\Services\Billing\AccountInvoiceRunner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Feature 098 (Rechnungs-Modus): fakturiert den Vormonat aller aktiven
 * invoice-Mode-Sonderkonditionen über die normale Pipeline. Idempotent über
 * das exported-Flag — Mehrfachläufe erzeugen keine Doppelbelege.
 */
class GenerateAccountInvoicesCommand extends Command {
    protected $signature = 'customer-billing:generate-invoices';

    protected $description = 'Erzeugt Monatsrechnungen für Kunden-Sonderkonditionen im Rechnungs-Modus (Feature 098)';

    public function handle(AccountInvoiceRunner $runner): int {
        $lock = Cache::lock('customer-billing:generate-invoices', 600);
        if (! $lock->get()) {
            $this->warn('Läuft bereits (Lease aktiv) — Abbruch.');

            return self::SUCCESS;
        }

        try {
            $result = $runner->runDue();
            $this->info(sprintf(
                'Rechnungen: %d erzeugt, %d übersprungen (leer/bereits fakturiert), %d blockiert (externe Rechnungshoheit).',
                $result['created'],
                $result['skipped'],
                $result['failed'],
            ));
        } finally {
            $lock->release();
        }

        return self::SUCCESS;
    }
}
