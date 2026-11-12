<?php
/*
 * Created on   : Wed May 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VacationStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Vacation;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\HasStatusTransitions;

enum VacationStatus: string implements HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    /** Zur Korrektur an die antragstellende Person zurückgegeben (MVP-1554). */
    case Revision = 'revision';

    public function label(): string {
        return (string) __('enums.vacation.status.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::Approved => 'success',
            self::Rejected => 'error',
            self::Cancelled => 'ghost',
            self::Pending => 'warning',
            self::Revision => 'info',
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Pending => [self::Approved, self::Rejected, self::Revision, self::Cancelled],
            // Geändert und neu eingereicht oder zurückgezogen.
            self::Revision => [self::Pending, self::Cancelled],
            self::Approved => [self::Cancelled],
            self::Rejected, self::Cancelled => [],
        };
    }
}
