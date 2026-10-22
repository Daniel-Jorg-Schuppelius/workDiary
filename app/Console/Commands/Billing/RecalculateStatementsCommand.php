<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecalculateStatementsCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Billing;

use App\Models\Billing\CustomerBillingAgreement;
use App\Models\Platform\Organization;
use App\Services\Billing\CustomerAccountStatementService;
use App\Support\OrganizationContext;
use Illuminate\Console\Command;

/**
 * Nächtliche Sicherung der Kundenkonten (Vollscan 2026-10-21, lk-13): legt
 * den neuen Monat an und rechnet offene Monate nach, auch wenn ein
 * Änderungsereignis verloren ging. Das Kundenportal rechnet nicht mehr selbst.
 */
class RecalculateStatementsCommand extends Command {
    protected $signature = 'customer-billing:recalculate-statements';

    protected $description = 'Rechnet die offenen Monate aller Kundenkonten nach (Monatskette, nächtlich)';

    public function handle(CustomerAccountStatementService $statements): int {
        $count = 0;
        $agreements = CustomerBillingAgreement::query()->withoutGlobalScopes()->where('active', true)->get();
        foreach ($agreements as $agreement) {
            $organization = Organization::query()->find($agreement->organization_id);
            if (! $agreement->keepsLedger() || ! $organization instanceof Organization) {
                continue;
            }
            OrganizationContext::run($organization, static fn () => $statements->recalculateOpen($agreement));
            $count++;
        }
        $this->info(sprintf('%d Kundenkonten nachgerechnet.', $count));

        return self::SUCCESS;
    }
}
