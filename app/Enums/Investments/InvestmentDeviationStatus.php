<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvestmentDeviationStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Investments;

use App\Enums\Concerns\HasTransitions;
use App\Enums\Contracts\HasStatusTransitions;

/** Stand einer Abweichung (MVP-206): offen, bis die Entscheidung fällt. */
enum InvestmentDeviationStatus: string implements HasStatusTransitions {
    use HasTransitions;

    case Open = 'open';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string {
        return (string) __('values.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Approved => 'success',
            self::Rejected => 'error',
            self::Open => 'warning',
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Open => [self::Approved, self::Rejected],
            self::Approved, self::Rejected => [],
        };
    }
}
