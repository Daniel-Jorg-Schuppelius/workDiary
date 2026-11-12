<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillingTransferCompleted.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Events\Finance;

use Illuminate\Foundation\Events\Dispatchable;

/** Übergabe ans Buchhaltungsprogramm vollzogen (MVP-1596), die Quellen sind verbraucht. */
final class BillingTransferCompleted {
    use Dispatchable;

    public function __construct(
        public readonly int $organizationId,
        public readonly int $billingTransferId,
    ) {}
}
