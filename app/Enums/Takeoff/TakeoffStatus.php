<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TakeoffStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Takeoff;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\{HasLabel, HasStatusTransitions};

/** Stand eines Aufmaßblatts (MVP-1058): abgeschlossene Blätter sind gesperrt und übernehmbar. */
enum TakeoffStatus: string implements HasLabel, HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Draft = 'draft';
    case Completed = 'completed';

    public function label(): string {
        return (string) __('enums.takeoff.status.' . $this->value);
    }

    public function tone(): string {
        return $this === self::Completed ? 'success' : 'ghost';
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Draft => [self::Completed],
            self::Completed => [self::Draft],
        };
    }
}
