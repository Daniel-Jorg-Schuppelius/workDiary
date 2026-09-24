<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MaintenanceWindowStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Asset;

use App\Enums\Concerns\HasTransitions;
use App\Enums\Contracts\{HasLabel, HasStatusTransitions};

/** Lebenszyklus eines Wartungsfensters (MVP-055, DoD 022). */
enum MaintenanceWindowStatus: string implements HasLabel, HasStatusTransitions {
    use HasTransitions;

    case Planned = 'planned';
    case Announced = 'announced';
    case Active = 'active';
    case Extended = 'extended';
    case Completed = 'completed';
    case RolledBack = 'rolled_back';
    case Cancelled = 'cancelled';

    public function label(): string {
        return (string) __('maintenance.window.status.' . $this->value);
    }

    /** Nicht-terminal — nur diese Fenster können wirksam werden. */
    public function isOpen(): bool {
        return in_array($this, self::open(), true);
    }

    /** @return list<self> */
    public static function open(): array {
        return [self::Planned, self::Announced, self::Active, self::Extended];
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Planned => [self::Announced, self::Active, self::Cancelled],
            self::Announced => [self::Active, self::Cancelled],
            self::Active => [self::Completed, self::Extended, self::RolledBack],
            self::Extended => [self::Completed, self::RolledBack],
            self::Completed, self::RolledBack, self::Cancelled => [],
        };
    }
}
