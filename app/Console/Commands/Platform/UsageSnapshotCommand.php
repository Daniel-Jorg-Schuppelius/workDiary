<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UsageSnapshotCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Platform;

use App\Services\Platform\TenantBillingService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/** Nutzungsstand je Mandant für die Abrechnung (MVP-956); Standard ist der Vormonat. */
class UsageSnapshotCommand extends Command {
    protected $signature = 'platform:usage-snapshot {--month= : Monat im Format JJJJ-MM}';

    protected $description = 'Hält Nutzer, aktive Nutzer, Speicher und Tarif je Organisation für die Nutzungsabrechnung fest.';

    public function handle(TenantBillingService $billing): int {
        $option = (string) ($this->option('month') ?? '');
        if ($option !== '' && preg_match('/^\d{4}-\d{2}$/', $option) !== 1) {
            $this->error('Monat bitte als JJJJ-MM angeben.');

            return self::FAILURE;
        }
        $month = $option !== '' ? CarbonImmutable::parse($option . '-01') : CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth();
        $count = $billing->snapshotAll($month);
        $this->info("Nutzungsstand {$month->format('m/Y')}: {$count} Organisationen.");

        return self::SUCCESS;
    }
}
