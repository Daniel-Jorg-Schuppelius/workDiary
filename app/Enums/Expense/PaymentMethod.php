<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PaymentMethod.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Expense;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum PaymentMethod: string implements HasLabel {
    use HasOptions;

    case PrivatePaid = 'private_paid';
    case CompanyCard = 'company_card';
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';

    public function label(): string {
        return (string) __('enums.expense.payment_method.' . $this->value);
    }

    /**
     * Markiert Zahlungsarten, die einer Erstattung an den Mitarbeiter
     * bedürfen (vs. Firmenmittel).
     */
    public function isReimbursable(): bool {
        return match ($this) {
            self::PrivatePaid, self::Cash => true,
            self::CompanyCard, self::BankTransfer => false,
        };
    }
}
