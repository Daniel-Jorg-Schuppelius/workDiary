<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubGroupMembershipStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Club;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\{HasLabel, HasStatusTransitions};

/**
 * Stand einer Gruppenzuordnung (MVP-842): Antrag → aktiv/abgelehnt,
 * aktiv → beendet. Beendete Zuordnungen bleiben als Historie erhalten.
 */
enum ClubGroupMembershipStatus: string implements HasLabel, HasStatusTransitions {
    use HasOptions;

    case Requested = 'requested';
    case Active = 'active';
    case Ended = 'ended';
    case Rejected = 'rejected';

    public function label(): string {
        return (string) __('enums.club.group-membership-status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Requested => 'warning',
            self::Active => 'success',
            self::Ended => 'ghost',
            self::Rejected => 'error',
        };
    }

    /** @return list<ClubGroupMembershipStatus> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Requested => [self::Active, self::Rejected],
            self::Active => [self::Ended],
            self::Ended, self::Rejected => [],
        };
    }
}
