<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MedicalCheckupKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Safety;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Art der arbeitsmedizinischen Vorsorge nach ArbMedVV (Feature 132):
 * Pflicht- (§ 4), Angebots- (§ 5) und Wunschvorsorge (§ 5a).
 */
enum MedicalCheckupKind: string implements HasLabel {
    use HasOptions;

    case Mandatory = 'mandatory';
    case Offered = 'offered';
    case Requested = 'requested';

    public function label(): string {
        return (string) __('enums.safety.checkup-kind.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Mandatory => 'error',
            self::Offered => 'info',
            self::Requested => 'ghost',
        };
    }
}
