<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubFeeRunStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Club;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasStatusTransitions;

/** Zustand eines Beitragslaufs (MVP-850): Entwurf mit Vorschau, freigegeben (Forderungen erzeugt), verworfen. */
enum ClubFeeRunStatus: string implements HasStatusTransitions {
    use HasOptions;

    case Draft = 'draft';
    case Released = 'released';
    case Cancelled = 'cancelled';

    public function label(): string {
        return (string) __('enums.club.fee-run-status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Draft => 'warning',
            self::Released => 'success',
            self::Cancelled => 'ghost',
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Draft => [self::Released, self::Cancelled],
            self::Released, self::Cancelled => [],
        };
    }
}
