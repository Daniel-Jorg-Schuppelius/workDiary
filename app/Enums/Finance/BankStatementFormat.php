<?php
/*
 * Created on   : Sat Jun 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BankStatementFormat.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Finance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Importformat eines Bankauszugs (Feature 045, „Priorität 3: Bankimport"):
 * CAMT.053 ist das bevorzugte Format, MT940 dient als Fallback. MVP-334
 * (Bauturbo A15) ergänzt den allgemeinen Finanzformat-Import: OFX (1.x SGML/
 * 2.x XML), QIF, QXF sowie PAIN.001 (Überweisungs-) und PAIN.008
 * (Lastschrift-Aufträge als angekündigte Zahlungen). MVP-1274: CAMT.052
 * (Tagesauszug) und CAMT.054 (Umsatzmeldung).
 */
enum BankStatementFormat: string implements HasLabel {
    use HasOptions;

    case Camt053 = 'camt053';
    case Camt052 = 'camt052';
    case Camt054 = 'camt054';
    case Mt940 = 'mt940';
    case Ofx = 'ofx';
    case Qif = 'qif';
    case Qxf = 'qxf';
    case Pain001 = 'pain001';
    case Pain008 = 'pain008';

    public function label(): string {
        return (string) __('enums.finance.bank-statement-format.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::Camt053 => 'info',
            self::Camt052, self::Camt054 => 'ghost',
            self::Mt940 => 'neutral',
            self::Ofx, self::Qif, self::Qxf => 'accent',
            self::Pain001, self::Pain008 => 'warning',
        };
    }

    /**
     * Vorläufige Formate (MVP-1274): Vorschau ohne Abgleich, Buchung und
     * Kennzahlen, bis der verbindliche Auszug desselben Kontos sie ersetzt.
     */
    public function isProvisional(): bool {
        return match ($this) {
            self::Camt052, self::Camt054, self::Pain001, self::Pain008 => true,
            default => false,
        };
    }
}
