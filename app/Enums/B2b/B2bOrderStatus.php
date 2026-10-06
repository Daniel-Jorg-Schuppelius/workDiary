<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : B2bOrderStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\B2b;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\HasStatusTransitions;

/** Status einer eingegangenen openTRANS-Bestellung (Feature 099, MVP-458). */
enum B2bOrderStatus: string implements HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Open = 'open';
    case Booked = 'booked';
    case Dismissed = 'dismissed';

    public function label(): string {
        return (string) __('b2b_catalog.status.order_' . $this->value);
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Open => [self::Booked, self::Dismissed],
            self::Booked, self::Dismissed => [],
        };
    }
}
