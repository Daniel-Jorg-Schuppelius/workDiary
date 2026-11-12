<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TaxCodeDirection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Finance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Wirkungsrichtung eines Steuerkennzeichens (Feature 125, MVP-672):
 * Umsatzsteuer (Ausgangsseite), Vorsteuer (Eingangsseite), steuerfrei oder
 * beides zugleich — die Steuerschuld des Empfängers (§ 13b UStG,
 * innergemeinschaftlicher Erwerb, MVP-1621): Umsatzsteuer auf dem Steuerkonto,
 * Vorsteuer auf dem Vorsteuerkonto des Kennzeichens.
 *
 * Das Kennzeichen entscheidet **nicht** über die steuerliche Behandlung — die
 * kommt aus dem eingefrorenen `tax_context` des Belegs ({@see \App\Models\Finance\TaxRule}).
 * Es ordnet dieses Ergebnis nur einem Buchungskonto zu.
 */
enum TaxCodeDirection: string implements HasLabel {
    use HasOptions;

    case Output = 'output';
    case Input = 'input';
    case None = 'none';
    case SelfAssessed = 'both';

    public function label(): string {
        return (string) __('enums.finance.tax-code-direction.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Output => 'success',
            self::Input => 'info',
            self::None => 'ghost',
            self::SelfAssessed => 'warning',
        };
    }

    /** Steht die Bemessungsgrundlage im Soll (Aufwand) statt im Haben (Erlös)? */
    public function basedOnDebit(): bool {
        return $this === self::Input || $this === self::SelfAssessed;
    }
}
