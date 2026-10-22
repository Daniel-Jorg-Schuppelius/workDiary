<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecalculateStatementsForTimeEntry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Listeners\Billing;

use App\Events\Time\TimeEntryBillingChanged;
use App\Jobs\Billing\RecalculateAccountStatements;
use App\Models\Billing\CustomerBillingAgreement;
use App\Models\Project\Project;

/** Kundenkonten der betroffenen Projekte nachrechnen; das Kundenportal liest nur noch. */
final class RecalculateStatementsForTimeEntry {
    public function handle(TimeEntryBillingChanged $event): void {
        $projectIds = array_values(array_unique(array_filter($event->projectIds)));
        if ($projectIds === []) {
            return;
        }
        $customerIds = Project::query()->withoutGlobalScopes()->whereKey($projectIds)->whereNotNull('customer_id')->pluck('customer_id');
        if ($customerIds->isEmpty()) {
            return;
        }
        $agreements = CustomerBillingAgreement::query()->withoutGlobalScopes()->whereIn('customer_id', $customerIds)->get();
        foreach ($agreements as $agreement) {
            if ($agreement->keepsLedger()) {
                RecalculateAccountStatements::dispatch((int) $agreement->id);
            }
        }
    }
}
