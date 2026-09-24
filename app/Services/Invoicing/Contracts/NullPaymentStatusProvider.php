<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NullPaymentStatusProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\Contracts;

use App\Models\Invoicing\Invoice;

/** Ohne Finanzmodul gibt es keine Bankzuordnung. */
final class NullPaymentStatusProvider implements PaymentStatusProvider {
    public function allocatedSum(Invoice $invoice): float {
        return 0.0;
    }
}
