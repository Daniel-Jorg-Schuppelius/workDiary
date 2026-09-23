<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubFeePaymentMethod.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Club;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Zahlweg einer Beitragszahlung (MVP-851): Überweisung, bar, SEPA-Lastschrift, sonstiges. */
enum ClubFeePaymentMethod: string implements HasLabel {
    use HasOptions;

    case Transfer = 'transfer';
    case Cash = 'cash';
    case Sepa = 'sepa';
    case Other = 'other';

    public function label(): string {
        return (string) __('enums.club.fee-payment-method.' . $this->value);
    }
}
