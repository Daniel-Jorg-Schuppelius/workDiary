<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PaymentRunKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Finance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** SEPA-Zahlungsausgang (Feature 120, MVP-609). */
enum PaymentRunKind: string implements HasLabel {
    use HasOptions;

    case CreditTransfer = 'credit_transfer';
    case DirectDebit = 'direct_debit';

    public function label(): string {
        return (string) __('enums.payment_run_kind.' . $this->value);
    }
}
