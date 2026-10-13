<?php
/*
 * Created on   : Mon Oct 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillingRouteNumberSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\Finance\BillingMode;
use App\Models\Customer\Customer;
use App\Models\Platform\Organization;
use App\Services\Numbering\Contracts\BillingRouteNumbers;

/** Fakturierungsweg für die Seite „Nummernkreise“ (MVP-1149). */
class BillingRouteNumberSource implements BillingRouteNumbers {
    public function __construct(private readonly BillingModeResolver $modes) {}

    public function externalProgram(Organization $organization): ?string {
        $mode = $this->modes->forOrganization($organization);

        return $mode->isExternal() ? $mode->programName() : null;
    }

    public function deviations(Organization $organization): array {
        $orgMode = $this->modes->forOrganization($organization);

        $counts = Customer::query()
            ->where('organization_id', $organization->id)
            ->whereNull('archived_at')
            ->whereNotNull('billing_mode')
            ->where('billing_mode', '!=', $orgMode->value)
            ->toBase()
            ->selectRaw('billing_mode, count(*) as aggregate')
            ->groupBy('billing_mode')
            ->pluck('aggregate', 'billing_mode');

        $deviations = [];
        foreach ($counts as $value => $count) {
            $mode = BillingMode::tryFrom((string) $value);
            if ($mode !== null) {
                $deviations[] = ['program' => $mode->programName(), 'count' => (int) $count, 'local' => ! $mode->isExternal()];
            }
        }

        return $deviations;
    }
}
