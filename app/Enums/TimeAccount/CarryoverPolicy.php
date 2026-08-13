<?php
/*
 * Created on   : Thu Aug 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CarryoverPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\TimeAccount;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Übertragsregel eines Zeitkontos (MVP-526). */
enum CarryoverPolicy: string implements HasLabel {
    use HasOptions;

    /** Kumulierend ohne Grenze. */
    case Carry = 'carry';

    /** Kumulierend, aber Kappung auf `cap_amount` beim Monatsabschluss. */
    case Cap = 'cap';

    public function label(): string {
        return match ($this) {
            self::Carry => __('Übertrag (kumulierend)'),
            self::Cap   => __('Kappung beim Monatsabschluss'),
        };
    }
}
