<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubAdmissionMode.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Club;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Aufnahmemodus einer Vereinsgruppe (MVP-842): die Leitung nimmt direkt auf,
 * oder ein Antrag wartet auf Freigabe.
 */
enum ClubAdmissionMode: string implements HasLabel {
    use HasOptions;

    case Leader = 'leader';
    case Application = 'application';

    public function label(): string {
        return (string) __('enums.club.admission-mode.' . $this->value);
    }
}
