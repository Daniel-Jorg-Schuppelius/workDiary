<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BoqCallOffStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Gaeb;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\{HasLabel, HasStatusTransitions};

/** Stand eines Abrufs aus einem Rahmen-LV (MVP-931). */
enum BoqCallOffStatus: string implements HasLabel, HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Draft = 'draft';
    case Ordered = 'ordered';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string {
        return (string) __('gaeb.call_off.status.' . $this->value);
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Draft => [self::Ordered, self::Cancelled],
            self::Ordered => [self::Completed, self::Cancelled],
            self::Completed => [self::Ordered],
            self::Cancelled => [],
        };
    }

    public function isBillable(): bool {
        return $this === self::Ordered || $this === self::Completed;
    }
}
