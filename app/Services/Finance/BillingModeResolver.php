<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillingModeResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Finance;

use App\Enums\Finance\BillingMode;
use App\Models\Customer;

/**
 * Löst den effektiven Fakturierungsweg eines Kunden auf (Feature 045,
 * „Führendes System"). Kaskade:
 *
 *   1. Kunden-Override (customers.billing_mode)
 *   2. Org-Default (organizations.settings['billing_mode'])
 *   3. Fallback: WorkDiary führt (lokale Rechnungserstellung)
 */
class BillingModeResolver {
    public function effectiveFor(Customer $customer): BillingMode {
        $override = $customer->billing_mode;
        if ($override instanceof BillingMode) {
            return $override;
        }

        $orgSetting = data_get($customer->organization?->settings, 'billing_mode');
        if (is_string($orgSetting) && $orgSetting !== '') {
            $mode = BillingMode::tryFrom($orgSetting);
            if ($mode instanceof BillingMode) {
                return $mode;
            }
        }

        return BillingMode::Workdiary;
    }
}
