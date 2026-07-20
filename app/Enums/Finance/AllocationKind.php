<?php
/*
 * Created on   : Sat Jun 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AllocationKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Finance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Art einer bestätigten Zahlungszuordnung (Feature 045, „Priorität 3").
 *
 *   payment       = vollständige Zahlung einer Rechnung (Σ ≥ Soll − Skonto-Toleranz)
 *   partial       = Teilzahlung (Rechnung bleibt offen)
 *   overpayment   = Überzahlung (mehr als der offene Betrag)
 *   reimbursement = Erstattung einer freigegebenen Spese (direction=debit)
 *   chargeback    = Rückläufer-Kompensation (MVP-334): NEGATIVER Betrag auf dem
 *                   Rückläufer-Umsatz, der die ursprüngliche Zuordnung GoBD-
 *                   konform kompensiert (Original bleibt als Historie aktiv).
 */
enum AllocationKind: string implements HasLabel {
    use HasOptions;

    case Payment = 'payment';
    case Partial = 'partial';
    case Overpayment = 'overpayment';
    case Reimbursement = 'reimbursement';
    case Chargeback = 'chargeback';

    /** Akzeptierter Skontoabzug als Erlösschmälerung (Vollaudit 2026-07, N12). */
    case Skonto = 'skonto';

    public function label(): string {
        return (string) __('enums.finance.allocation-kind.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::Payment => 'success',
            self::Partial => 'warning',
            self::Overpayment => 'info',
            self::Reimbursement => 'accent',
            self::Chargeback => 'error',
            self::Skonto => 'ghost',
        };
    }
}
