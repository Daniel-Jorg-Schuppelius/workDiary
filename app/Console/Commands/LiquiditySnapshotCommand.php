<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LiquiditySnapshotCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Finance\BankAccount;
use App\Models\Platform\Organization;
use App\Services\Accounting\LiquiditySnapshotService;
use App\Support\{OrganizationContext, Tz};
use Illuminate\Console\Command;

/** MVP-984: Wochenstand der Liquiditätsvorschau je Organisation mit Bankkonto festhalten. */
class LiquiditySnapshotCommand extends Command {
    protected $signature = 'accounting:liquidity-snapshot';

    protected $description = 'Hält den Wochenstand der 13-Wochen-Liquiditätsvorschau für den Plan/Ist-Vergleich fest.';

    public function handle(LiquiditySnapshotService $snapshots): int {
        $count = 0;
        $organizationIds = BankAccount::query()->withoutGlobalScopes()->distinct()->pluck('organization_id');
        foreach (Organization::query()->whereIn('id', $organizationIds)->get() as $organization) {
            OrganizationContext::run($organization, static fn () => $snapshots->take($organization, Tz::now()));
            $count++;
        }
        $this->info(sprintf('%d Wochenstände festgehalten.', $count));

        return self::SUCCESS;
    }
}
