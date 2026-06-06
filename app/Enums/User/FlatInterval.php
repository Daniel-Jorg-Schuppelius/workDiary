<?php
/*
 * Created on   : Sat Jun 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FlatInterval.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\User;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Abrechnungsintervall einer Pauschale (compensation_model = pauschal).
 *
 *  - Monatlich:  wiederkehrend je Kalendermonat
 *  - ProEinsatz: je Einsatz/Vorgang
 *  - Einmalig:   einmaliger Festbetrag
 *
 * Labels über `user.flat_interval.<value>` übersetzt.
 */
enum FlatInterval: string implements HasLabel {
    use HasOptions;

    case Monatlich = 'monatlich';
    case ProEinsatz = 'pro_einsatz';
    case Einmalig = 'einmalig';

    public function label(): string {
        return (string) __('user.flat_interval.' . $this->value);
    }
}
