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
 * Umsatzsteuer (Ausgangsseite), Vorsteuer (Eingangsseite) oder steuerfrei.
 *
 * Das Kennzeichen entscheidet **nicht** über die steuerliche Behandlung — die
 * kommt aus dem eingefrorenen `tax_context` des Belegs ({@see \App\Models\TaxRule}).
 * Es ordnet dieses Ergebnis nur einem Buchungskonto zu.
 */
enum TaxCodeDirection: string implements HasLabel {
    use HasOptions;

    case Output = 'output';
    case Input = 'input';
    case None = 'none';

    public function label(): string {
        return (string) __('enums.finance.tax-code-direction.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Output => 'success',
            self::Input => 'info',
            self::None => 'ghost',
        };
    }
}
