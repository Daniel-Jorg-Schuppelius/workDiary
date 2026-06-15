<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AvailabilityKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Shift;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Art eines Verfügbarkeitsfensters (Feature 007).
 */
enum AvailabilityKind: string implements HasLabel {
    use HasOptions;

    case Available = 'available';
    case Unavailable = 'unavailable';
    case Preferred = 'preferred';

    public function label(): string {
        return (string) __('enums.shift.availability_kind.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Available => 'success',
            self::Unavailable => 'error',
            self::Preferred => 'info',
        };
    }
}
