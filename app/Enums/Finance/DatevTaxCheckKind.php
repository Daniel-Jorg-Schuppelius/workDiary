<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatevTaxCheckKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Finance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Befundart der Steuerprüfung vor dem DATEV-Export (Feature 207, MVP-1661).
 * Ob ein Befund den Export sperrt, entscheidet der Befund selbst — eine
 * fehlende USt-IdNr. sperrt bei der innergemeinschaftlichen Lieferung, beim
 * inländischen § 13b ist sie nur ein Hinweis.
 */
enum DatevTaxCheckKind: string implements HasLabel {
    use HasOptions;

    /** Erlös- oder Aufwandszeile mit Steuerpflicht ohne Kennzeichen. */
    case MissingTaxCode = 'missing_tax_code';

    /** Innergemeinschaftliche Lieferung oder § 13b ohne USt-IdNr. des Partners. */
    case MissingVatId = 'missing_vat_id';

    /** Buchung auf einem stillgelegten Konto. */
    case InactiveAccount = 'inactive_account';

    /** Die Exportlogik hebt die Automatik mit BU 40 auf. */
    case AutomaticSuspended = 'automatic_suspended';

    /** Satz der Zeile weicht vom Kennzeichen des Automatikkontos ab. */
    case RateMismatch = 'rate_mismatch';

    public function label(): string {
        return (string) __('enums.finance.datev-tax-check-kind.' . $this->value);
    }
}
