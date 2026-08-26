<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PostingAccountRole.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Finance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Rolle eines Kontos in einem Buchungsvorschlag (Feature 125, MVP-673).
 *
 * Die Adapter kennen Rollen, keine Kontonummern: „Erlöse 19 %" ist eine
 * Organisationsentscheidung, „hier steht der Erlös" eine fachliche. Das
 * Mapping zwischen beidem sind die versionierten Buchungsregeln.
 */
enum PostingAccountRole: string implements HasLabel {
    use HasOptions;

    /** Forderungen aus Lieferungen und Leistungen. */
    case Receivable = 'receivable';

    /** Erlöse. */
    case Revenue = 'revenue';

    /** Umsatzsteuer (Ausgangsseite). */
    case TaxOutput = 'tax_output';

    /** Verbindlichkeiten aus Lieferungen und Leistungen. */
    case Payable = 'payable';

    /** Aufwand. */
    case Expense = 'expense';

    /** Vorsteuer (Eingangsseite). */
    case TaxInput = 'tax_input';

    /** Kassenkonto. */
    case Cash = 'cash';

    /** Verbindlichkeit gegenüber Mitarbeitenden (Auslagen). */
    case EmployeePayable = 'employee_payable';

    /** Bankkonto (Zahlungsein- und -ausgang). */
    case Bank = 'bank';

    /** Gewährter Skonto (Erlösschmälerung bzw. Ertrag auf der Eingangsseite). */
    case Discount = 'discount';

    /** Anlagenkonto (Sachanlage, Haben-Seite der direkten AfA — Feature 133). */
    case FixedAsset = 'fixed_asset';

    /** AfA-Aufwand (Abschreibungen auf Sachanlagen — Feature 133). */
    case Depreciation = 'depreciation';

    public function label(): string {
        return (string) __('enums.finance.posting-account-role.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Receivable, self::Payable, self::EmployeePayable => 'info',
            self::Revenue => 'success',
            self::Expense, self::Depreciation => 'error',
            self::TaxOutput, self::TaxInput => 'warning',
            self::Cash, self::Bank, self::FixedAsset => 'secondary',
            self::Discount => 'accent',
        };
    }
}
