<?php
/*
 * Created on   : Fri Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GenerateRecurringInvoicesCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands;

use App\Services\Invoicing\RecurringInvoiceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * MVP-415: fällige Abrechnungspläne in Rechnungs-ENTWÜRFE überführen —
 * idempotent (invoice_schedule_runs), nie Auto-Ausstellung/-Versand.
 */
class GenerateRecurringInvoicesCommand extends Command {
    protected $signature = 'invoices:generate-recurring';

    protected $description = 'Erzeugt Rechnungsentwürfe aus fälligen Abrechnungsplänen (MVP-415)';

    public function handle(RecurringInvoiceService $service): int {
        $lock = Cache::lock('invoices:generate-recurring', 600);
        if (! $lock->get()) {
            $this->warn('Läuft bereits (Lease aktiv) — Abbruch.');

            return self::SUCCESS;
        }

        try {
            $result = $service->generateDue();
            $this->info(sprintf(
                'Entwürfe: %d erzeugt, %d Pläne blockiert (externe Rechnungshoheit), %d beendet.',
                $result['created'],
                $result['blocked'],
                $result['ended'],
            ));
        } finally {
            $lock->release();
        }

        return self::SUCCESS;
    }
}
