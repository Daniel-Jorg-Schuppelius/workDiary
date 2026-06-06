<?php
/*
 * Created on   : Sat Jun 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CompensationModel.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\User;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Vergütungsmodell eines Mitarbeiters.
 *
 *  - Payroll:        intern, deutsche Lohnabrechnung (Steuerklasse/SV, Minijob …)
 *  - Pauschal:       externe(r) Mitarbeiter(in), Festbetrag je Intervall
 *  - NachZeitaufwand: externe(r) Mitarbeiter(in), Stundensatz × erfasste Zeit
 *
 * Werte sind stabil (DB), Labels über `user.compensation_model.<value>` übersetzt.
 * "Extern" ist alles außer Payroll (siehe User::isExternal()).
 */
enum CompensationModel: string implements HasLabel {
    use HasOptions;

    case Payroll = 'payroll';
    case Pauschal = 'pauschal';
    case NachZeitaufwand = 'nach_zeitaufwand';

    public function label(): string {
        return (string) __('user.compensation_model.' . $this->value);
    }

    /** True für externe Vergütungsmodelle (nicht über die deutsche Lohnabrechnung). */
    public function isExternal(): bool {
        return $this !== self::Payroll;
    }
}
