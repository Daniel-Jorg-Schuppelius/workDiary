<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShiftPreference.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Shift;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Wunsch oder Abneigung für eine konkrete Schicht (Feature 007).
 */
enum ShiftPreference: string implements HasLabel {
    use HasOptions;

    case Want = 'want';
    case Avoid = 'avoid';

    public function label(): string {
        return (string) __('enums.shift.preference.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Want => 'success',
            self::Avoid => 'warning',
        };
    }
}
