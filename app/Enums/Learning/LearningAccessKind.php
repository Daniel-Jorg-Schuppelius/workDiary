<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningAccessKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Learning;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Zugangsart eines Kurses (Feature 149; LearnDash-Pendant open/free/paynow/
 * closed). Ein Abo-Modell gibt es bewusst nicht — die Faktura führt.
 */
enum LearningAccessKind: string implements HasLabel {
    use HasOptions;

    case Open = 'open';
    case Enrolled = 'enrolled';
    case Bookable = 'bookable';
    case Closed = 'closed';

    public function label(): string {
        return (string) __('enums.learning.access-kind.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Open => 'success',
            self::Enrolled => 'info',
            self::Bookable => 'warning',
            self::Closed => 'neutral',
        };
    }
}
