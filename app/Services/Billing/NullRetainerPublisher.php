<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NullRetainerPublisher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Billing\CustomerBillingAgreement;
use App\Models\Invoicing\Invoice;
use App\Services\Billing\Contracts\RetainerPublisher;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/** Kein Buchhaltungsprogramm für Pauschalen angebunden: klare Meldung statt stillem No-op. */
final class NullRetainerPublisher implements RetainerPublisher {
    public function label(): ?string {
        return null;
    }

    public function isConfigured(): bool {
        return false;
    }

    public function pushMonthlyRetainer(CustomerBillingAgreement $agreement, int $year, int $month): ?Invoice {
        throw ValidationException::withMessages(['agreement' => __('customer-billing.retainer_no_channel')]);
    }

    public function pushTrueUp(CustomerBillingAgreement $agreement, ?CarbonInterface $cutoff = null): Invoice {
        throw ValidationException::withMessages(['agreement' => __('customer-billing.retainer_no_channel')]);
    }
}
