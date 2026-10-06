<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AgileSprintStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Agile;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\HasStatusTransitions;

/** Stand eines Sprints (Feature 064, MVP-142): geplant → aktiv → abgeschlossen oder abgebrochen, kein Wiederöffnen. */
enum AgileSprintStatus: string implements HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Planned = 'planned';
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string {
        return match ($this) {
            self::Planned => (string) __('geplant'),
            self::Active => (string) __('aktiv'),
            self::Completed => (string) __('abgeschlossen'),
            self::Cancelled => (string) __('abgebrochen'),
        };
    }

    public function tone(): string {
        return match ($this) {
            self::Planned => 'neutral',
            self::Active => 'success',
            self::Completed => 'info',
            self::Cancelled => 'error',
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Planned => [self::Active, self::Cancelled],
            self::Active => [self::Completed, self::Cancelled],
            self::Completed, self::Cancelled => [],
        };
    }
}
