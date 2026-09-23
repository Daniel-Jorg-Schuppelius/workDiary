<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubCountingBasis.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Club;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Zählzeitraum der Anwesenheit je Zielgrad (MVP-846): seit Vorgrad (Folgetag), seit Eintritt oder festes Fenster vor dem Prüfungstag. */
enum ClubCountingBasis: string implements HasLabel {
    use HasOptions;

    case SincePreviousGrade = 'since_previous_grade';
    case SinceMembership = 'since_membership';
    case WindowMonths = 'window_months';

    public function label(): string {
        return (string) __('enums.club.counting-basis.' . $this->value);
    }
}
