<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FilingObligationKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Finance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Art einer steuerlichen Meldepflicht (Feature 125, MVP-686). */
enum FilingObligationKind: string implements HasLabel {
    use HasOptions;

    /** Umsatzsteuer-Voranmeldung (§ 18 Abs. 1 UStG). */
    case VatAdvance = 'vat_advance';

    /** Sondervorauszahlung zur Dauerfristverlängerung (§ 47 UStDV). */
    case SpecialPrepayment = 'special_prepayment';

    /** Zusammenfassende Meldung (§ 18a UStG). */
    case Recapitulative = 'recapitulative';

    /** Umsatzsteuer-Jahreserklärung (§ 149 AO). */
    case AnnualReturn = 'annual_return';

    public function label(): string {
        return (string) __('enums.finance.filing-obligation-kind.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::VatAdvance => 'info',
            self::SpecialPrepayment => 'accent',
            self::Recapitulative => 'warning',
            self::AnnualReturn => 'neutral',
        };
    }

    /**
     * Verlängert die Dauerfristverlängerung diese Frist?
     *
     * Für die Zusammenfassende Meldung ausdrücklich **nicht** (§ 18a Abs. 1
     * UStG) — der häufigste Praxisfehler.
     */
    public function extendable(): bool {
        return $this === self::VatAdvance;
    }
}
