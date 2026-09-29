<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LicenseAssignmentEnd.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Reselling;

use App\Enums\Contracts\HasLabel;

/** Wie eine Verkaufszuordnung endete (MVP-1024). */
enum LicenseAssignmentEnd: string implements HasLabel {
    /** Rücknahme: die Lizenz ist danach gesperrt, nie frei. */
    case Returned = 'returned';
    /** Berichtigung: dieselbe Lizenz ging nahtlos an einen anderen Kunden. */
    case Corrected = 'corrected';

    public function label(): string {
        return (string) __('resale.license.end.' . $this->value);
    }
}
