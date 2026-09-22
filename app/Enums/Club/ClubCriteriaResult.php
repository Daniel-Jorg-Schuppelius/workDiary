<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubCriteriaResult.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Club;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Ergebnis der Kriterienprüfung einer Gruppe (MVP-842): erfüllt, Alter
 * unter/über der Grenze oder Prüfung erforderlich (fehlendes Geburtsdatum).
 * Dient zugleich als Grund eines Wechsel-/Prüfvorschlags.
 */
enum ClubCriteriaResult: string implements HasLabel {
    use HasOptions;

    case Met = 'met';
    case AgeBelow = 'age_below';
    case AgeAbove = 'age_above';
    case ReviewRequired = 'review_required';

    public function label(): string {
        return (string) __('enums.club.criteria-result.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Met => 'success',
            self::AgeBelow, self::AgeAbove => 'warning',
            self::ReviewRequired => 'info',
        };
    }

    public function isMet(): bool {
        return $this === self::Met;
    }
}
