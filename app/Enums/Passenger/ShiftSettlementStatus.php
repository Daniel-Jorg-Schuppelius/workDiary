<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShiftSettlementStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Passenger;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\HasStatusTransitions;

/** Stand einer Fahrer-/Schichtabrechnung (MVP-456): offen, glatt abgeschlossen oder mit begründeter Differenz. */
enum ShiftSettlementStatus: string implements HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Open = 'open';
    case Balanced = 'balanced';
    case Disputed = 'disputed';

    public function label(): string {
        return (string) __('passenger.settlement_status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Balanced => 'success',
            self::Disputed => 'warning',
            self::Open => 'neutral',
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Open => [self::Balanced, self::Disputed],
            self::Balanced, self::Disputed => [],
        };
    }
}
