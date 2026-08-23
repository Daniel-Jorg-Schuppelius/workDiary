<?php
/*
 * Created on   : Sat Aug 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TaxationMethod.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Finance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Art der Umsatzsteuerberechnung (Feature 125, MVP-679).
 *
 * Soll-Versteuerung (§ 16 Abs. 1 UStG) ist der gesetzliche Regelfall: Die
 * Steuer entsteht mit der Leistung. Die Ist-Versteuerung (§ 20 UStG) ist
 * genehmigungspflichtig und knüpft an die Vereinnahmung an.
 *
 * Die Wahl betrifft ausschließlich die Ausgangsseite. Die Vorsteuer bleibt in
 * beiden Fällen abziehbar, sobald Leistung und Rechnung vorliegen — deshalb
 * verzweigt nur die Umsatzsteuer-Auswertung, nicht der Buchungskern.
 */
enum TaxationMethod: string implements HasLabel {
    use HasOptions;

    /** Soll-Versteuerung — Steuer entsteht mit der Leistung. */
    case Debit = 'debit';

    /** Ist-Versteuerung — Steuer entsteht mit der Vereinnahmung. */
    case Credit = 'credit';

    public function label(): string {
        return (string) __('enums.finance.taxation-method.' . $this->value);
    }

    public function tone(): string {
        return $this === self::Debit ? 'info' : 'accent';
    }

    /** Knüpft die Steuer an die Zahlung statt an den Beleg? */
    public function followsPayments(): bool {
        return $this === self::Credit;
    }
}
