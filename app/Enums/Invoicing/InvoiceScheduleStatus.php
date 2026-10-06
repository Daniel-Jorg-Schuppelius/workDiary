<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceScheduleStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Invoicing;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\HasStatusTransitions;

/** Stand eines Abrechnungsplans (MVP-415): aussetzen und fortsetzen geht beliebig, beenden ist endgültig. */
enum InvoiceScheduleStatus: string implements HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Active = 'active';
    case Paused = 'paused';
    case Ended = 'ended';

    public function label(): string {
        return match ($this) {
            self::Active => (string) __('Aktiv'),
            self::Paused => (string) __('Pausiert'),
            self::Ended => (string) __('Beendet'),
        };
    }

    public function tone(): string {
        return match ($this) {
            self::Active => 'success',
            self::Paused => 'warning',
            self::Ended => 'neutral',
        };
    }

    /** Endzustand — ein beendeter Plan wird nicht wieder aktiviert. */
    public function isFinal(): bool {
        return $this->allowedTransitions() === [];
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Active => [self::Paused, self::Ended],
            self::Paused => [self::Active, self::Ended],
            self::Ended => [],
        };
    }
}
