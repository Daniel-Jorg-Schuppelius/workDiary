<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetInspectionOrderStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\AssetCompliance;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\{HasLabel, HasStatusTransitions};

/** Stand eines Prüfauftrags an einen Dienstleister (MVP-938). */
enum AssetInspectionOrderStatus: string implements HasLabel, HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Requested = 'requested';
    case Offered = 'offered';
    case Accepted = 'accepted';
    case Reported = 'reported';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string {
        return (string) __('inspection_order.status.' . $this->value);
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Requested => [self::Offered, self::Cancelled],
            self::Offered => [self::Accepted, self::Requested, self::Cancelled],
            self::Accepted => [self::Reported, self::Cancelled],
            self::Reported => [self::Completed, self::Accepted],
            self::Completed, self::Cancelled => [],
        };
    }
}
