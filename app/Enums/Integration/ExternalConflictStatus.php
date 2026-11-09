<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalConflictStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Integration;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\HasStatusTransitions;

/** Stand eines Konflikts mit einem Fremdsystem: offen, bis er lokal belassen oder kompensiert ist. */
enum ExternalConflictStatus: string implements HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Open = 'open';
    case ResolvedLocal = 'resolved_local';

    /** Durch fachliche Gegenbuchung ausgeglichen (Inventory-Outbox, MVP-072). */
    case Compensated = 'compensated';

    public function label(): string {
        return (string) __('inventory.conflict.status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Open => 'warning',
            self::Compensated => 'info',
            self::ResolvedLocal => 'success',
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Open => [self::ResolvedLocal, self::Compensated],
            self::ResolvedLocal, self::Compensated => [],
        };
    }
}
