<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DamageCaseStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Damage;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\{HasLabel, HasStatusTransitions};

/** Lebenszyklus eines Schadensfalls (MVP-919). */
enum DamageCaseStatus: string implements HasLabel, HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Reported = 'reported';
    case Submitted = 'submitted';
    case InReview = 'in_review';
    case Settled = 'settled';
    case Rejected = 'rejected';
    case Closed = 'closed';

    public function label(): string {
        return (string) __('damage.status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Reported => 'warning',
            self::Submitted, self::InReview => 'info',
            self::Settled => 'success',
            self::Rejected => 'error',
            self::Closed => 'ghost',
        };
    }

    public function isOpen(): bool {
        return ! in_array($this, [self::Closed], true);
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Reported => [self::Submitted, self::Closed],
            self::Submitted => [self::InReview, self::Settled, self::Rejected],
            self::InReview => [self::Settled, self::Rejected],
            self::Settled => [self::Closed],
            self::Rejected => [self::Submitted, self::Closed],
            self::Closed => [],
        };
    }
}
