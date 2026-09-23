<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubFeePaymentSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Club;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Herkunft einer Beitragszahlung (MVP-851): manuell erfasst, Bankabgleich, Einzugslauf, Rücklastschrift, Guthabenverrechnung. */
enum ClubFeePaymentSource: string implements HasLabel {
    use HasOptions;

    case Manual = 'manual';
    case Bank = 'bank';
    case Sepa = 'sepa';
    case Chargeback = 'chargeback';
    case Credit = 'credit';

    public function label(): string {
        return (string) __('enums.club.fee-payment-source.' . $this->value);
    }
}
