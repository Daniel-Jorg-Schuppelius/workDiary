<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalCondition.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Rental;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Zustandsstufe in Übergabe-/Rücknahmeprotokollen (MVP-263/265).
 */
enum RentalCondition: string implements HasLabel {
    use HasOptions;

    case New = 'new';
    case Good = 'good';
    case Used = 'used';
    case Worn = 'worn';
    case Damaged = 'damaged';

    public function label(): string {
        return match ($this) {
            self::New => (string) __('Neuwertig'),
            self::Good => (string) __('Gut'),
            self::Used => (string) __('Gebraucht'),
            self::Worn => (string) __('Abgenutzt'),
            self::Damaged => (string) __('Beschädigt'),
        };
    }
}
