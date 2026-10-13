<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillingModeResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Billing;

use App\Enums\Finance\BillingMode;
use App\Models\Customer\Customer;
use App\Models\Platform\Organization;

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

        return $this->forOrganization($customer->organization);
    }

    /**
     * Hoheits-Sperre (Feature 045): führt ein externes Programm die
     * Fakturierung des Kunden, entsteht in workDiary keine lokale Rechnung —
     * Zeiten und Material gehen als BillingTransfer an das führende System.
     * Gilt auch für Überführungen aus Angebot und Pro-forma (MVP-1152).
     *
     * @throws BillingModeLockedException
     */
    public function assertLocalInvoicing(Customer $customer): void {
        $mode = $this->effectiveFor($customer);
        if ($mode->isExternal()) {
            throw new BillingModeLockedException($mode);
        }
    }

    /** Org-Default ohne Kunden-Override; ohne Organisation führt WorkDiary. */
    public function forOrganization(?Organization $organization): BillingMode {
        $orgSetting = data_get($organization?->settings, 'billing_mode');

        return is_string($orgSetting) ? (BillingMode::tryFrom($orgSetting) ?? BillingMode::Workdiary) : BillingMode::Workdiary;
    }
}
