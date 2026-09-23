<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubLineupStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Club;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasStatusTransitions;

/** Aufstellung eines Spieltags: Entwurf der Leitung oder freigegeben (Nominierung). */
enum ClubLineupStatus: string implements HasStatusTransitions {
    use HasOptions;

    case Draft = 'draft';
    case Released = 'released';

    public function label(): string {
        return (string) __('enums.club.lineup-status.' . $this->value);
    }

    public function tone(): string {
        return $this === self::Released ? 'success' : 'warning';
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Draft => [self::Released],
            self::Released => [self::Draft],
        };
    }
}
