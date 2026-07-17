<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ManufacturingOrderStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Manufacturing;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Statusmodell eines Fertigungs-/Montageauftrags (Feature 047):
 *
 *   Entwurf → Freigegeben → In Arbeit → Abgeschlossen
 *                            ↘ Wartet ↘ Blockiert
 *   Entwurf/Freigegeben/In Arbeit → Abgebrochen
 *
 * Ein abgeschlossener Auftrag ist fachlich unveränderlich.
 */
enum ManufacturingOrderStatus: string implements HasLabel {
    use HasOptions;

    case Draft = 'draft';
    case Released = 'released';
    case InProgress = 'in_progress';
    case Waiting = 'waiting';
    case Blocked = 'blocked';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string {
        return __('manufacturing.status.' . $this->value);
    }

    public function isTerminal(): bool {
        return $this === self::Completed || $this === self::Cancelled;
    }

    /** @return list<self> */
    public function allowedNext(): array {
        return match ($this) {
            self::Draft => [self::Released, self::Cancelled],
            self::Released => [self::InProgress, self::Cancelled],
            self::InProgress => [self::Waiting, self::Blocked, self::Completed, self::Cancelled],
            self::Waiting, self::Blocked => [self::InProgress, self::Cancelled],
            self::Completed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool {
        return in_array($target, $this->allowedNext(), true);
    }
}
