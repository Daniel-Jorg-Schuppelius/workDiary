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
     * bedürfen: nur privat Verauslagtes. Barkasse, Firmenkarte und
     * Überweisung sind Firmenmittel.
     */
    public function isReimbursable(): bool {
        return match ($this) {
            self::PrivatePaid => true,
            self::Cash, self::CompanyCard, self::BankTransfer => false,
        };
    }

    /**
     * Speicherwerte der erstattungsfähigen Zahlungsarten — für Kennzahlen und Abgleich.
     *
     * @return list<string>
     */
    public static function reimbursableValues(): array {
        return array_values(array_map(
            static fn (self $method): string => $method->value,
            array_filter(self::cases(), static fn (self $method): bool => $method->isReimbursable()),
        ));
    }

    /**
     * In der UI zugelassene Zahlungsarten (W2.3): Whitelist aus
     * config/expenses.php; leere Liste = alle Enum-Cases.
     *
     * @return list<self>
     */
    public static function allowed(): array {
        $allowed = (array) config('expenses.allowed_payment_methods', []);

        if ($allowed === []) {
            return self::cases();
        }

        return array_values(array_filter(
            self::cases(),
            static fn (self $method): bool => in_array($method->value, $allowed, true),
        ));
    }
}
