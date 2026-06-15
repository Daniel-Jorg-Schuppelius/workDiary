<?php
/*
 * Created on   : Sat Jun 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TransactionDirection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Finance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;
use CommonToolkit\Enums\CreditDebit;

/**
 * Soll/Haben-Richtung eines Bankumsatzes aus KONTOSICHT (Feature 045,
 * „Fachliches Transfermodell · Bankumsatz").
 *
 *   credit = Gutschrift = Geld kommt auf das eigene Konto (CreditDebit::CREDIT)
 *   debit  = Lastschrift = Geld verlässt das eigene Konto  (CreditDebit::DEBIT)
 *
 * Diese Semantik ist gegen eine echte Fixture verifiziert: php-financial-formats
 * liefert für einen Gutschrifts-Eingang (Geld rein) `CreditDebit::CREDIT`
 * (siehe `CreditDebit`-Enum-Kommentar „Gutschrift / Haben" und den Test
 * `BankStatementImporterTest::test_credit_debit_semantics_*`). Nur `credit`-
 * Umsätze sind für den Forderungsabgleich (Zahlungseingang) relevant.
 */
enum TransactionDirection: string implements HasLabel {
    use HasOptions;

    case Credit = 'credit';
    case Debit = 'debit';

    /**
     * Bildet die KONTOSICHT-Richtung der Bibliothek auf das interne Enum ab.
     * CreditDebit::CREDIT (Haben/Gutschrift) ⇒ credit (Geld rein).
     */
    public static function fromCreditDebit(CreditDebit $cd): self {
        return $cd === CreditDebit::CREDIT ? self::Credit : self::Debit;
    }

    public function isCredit(): bool {
        return $this === self::Credit;
    }

    /** Vorzeichen für die Saldenkette (Haben +, Soll -). */
    public function sign(): int {
        return $this === self::Credit ? 1 : -1;
    }

    public function label(): string {
        return (string) __('enums.finance.transaction-direction.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::Credit => 'success',
            self::Debit => 'neutral',
        };
    }
}
