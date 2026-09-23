<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubAvailabilityStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Club;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Verfügbarkeit eines Mitglieds für einen Spieltag — eine Zusage ist keine Nominierung. */
enum ClubAvailabilityStatus: string implements HasLabel {
    use HasOptions;

    case Available = 'available';
    case Maybe = 'maybe';
    case Unavailable = 'unavailable';

    public function label(): string {
        return (string) __('enums.club.availability-status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Available => 'success',
            self::Maybe => 'warning',
            self::Unavailable => 'error',
        };
    }

    public function icon(): string {
        return match ($this) {
            self::Available => 'check_circle',
            self::Maybe => 'help',
            self::Unavailable => 'cancel',
        };
    }
}
