<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubHorseKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Club;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Schulpferd des Vereins oder Privatpferd eines Mitglieds (Feature 159, MVP-854). */
enum ClubHorseKind: string implements HasLabel {
    use HasOptions;

    case School = 'school';
    case Private = 'private';

    public function label(): string {
        return (string) __('enums.club.horse-kind.' . $this->value);
    }
}
