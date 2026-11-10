<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExpireRentalPrepaymentsCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Rental;

use App\Console\Concerns\IteratesModuleOrganizations;
use App\Services\Rental\RentalRequestService;
use Illuminate\Console\Command;

/**
 * Gibt unbezahlte Direktbuchungen nach Ablauf der Zahlungsfrist frei
 * (MVP-1515); für den Scheduler im Fünf-Minuten-Takt.
 */
class ExpireRentalPrepaymentsCommand extends Command {
    use IteratesModuleOrganizations;

    protected $signature = 'rental:expire-prepayments';

    protected $description = 'Gibt Direktbuchungen im Verleih frei, deren Online-Vorauszahlung bis zur Frist nicht eingegangen ist.';

    public function handle(RentalRequestService $requests): int {
        $expired = $requests->expireUnpaid($this->moduleOrganizationIds());
        $this->info("Unbezahlte Direktbuchungen freigegeben: {$expired}.");

        return self::SUCCESS;
    }
}
