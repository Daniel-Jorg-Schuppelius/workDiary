<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubEntryStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Club;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasStatusTransitions;

/** Wettkampfmeldung (Feature 159, MVP-855): gemeldet, zur Klärung (Startrecht), zurückgezogen. */
enum ClubEntryStatus: string implements HasStatusTransitions {
    use HasOptions;

    case Registered = 'registered';
    case NeedsReview = 'needs_review';
    case Withdrawn = 'withdrawn';

    public function label(): string {
        return (string) __('enums.club.entry-status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Registered => 'success',
            self::NeedsReview => 'warning',
            self::Withdrawn => 'ghost',
        };
    }

    public function isActive(): bool {
        return $this !== self::Withdrawn;
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Registered => [self::NeedsReview, self::Withdrawn],
            self::NeedsReview => [self::Registered, self::Withdrawn],
            self::Withdrawn => [self::Registered, self::NeedsReview],
        };
    }
}
