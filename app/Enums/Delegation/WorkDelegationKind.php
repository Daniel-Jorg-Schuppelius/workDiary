<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WorkDelegationKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Delegation;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Anlass einer Vertretung (Feature 202, MVP-1647). Sichtbar nur für die
 * vertretene Person und die Leitung — die Vertretung erfährt den Grund nicht.
 */
enum WorkDelegationKind: string implements HasLabel {
    use HasOptions;

    case Vacation = 'vacation';
    case Sickness = 'sickness';
    case Other = 'other';

    public function label(): string {
        return (string) __('enums.delegation.work_delegation_kind.' . $this->value);
    }

    public function icon(): string {
        return match ($this) {
            self::Vacation => 'beach_access',
            self::Sickness => 'sick',
            self::Other => 'event_busy',
        };
    }
}
