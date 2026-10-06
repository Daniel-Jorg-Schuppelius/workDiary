<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EmployeeDraftStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Applications;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\HasStatusTransitions;

/** Stand eines Mitarbeiter-Entwurfs (Feature 068, MVP-193): erst die bewusste Übernahme lädt ein. */
enum EmployeeDraftStatus: string implements HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Draft = 'draft';
    case Invited = 'invited';

    /** Im Schema vorgesehen, heute von keinem Code geschrieben. */
    case Discarded = 'discarded';

    public function label(): string {
        return (string) __('values.' . $this->value);
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Draft => [self::Invited],
            self::Invited, self::Discarded => [],
        };
    }
}
