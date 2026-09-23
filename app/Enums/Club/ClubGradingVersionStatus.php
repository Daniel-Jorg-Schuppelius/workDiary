<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubGradingVersionStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Club;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Zustand einer Regelversion (MVP-846): Entwurf, aktiv (gilt für neue Prüfungsangebote), abgelöst. */
enum ClubGradingVersionStatus: string implements HasLabel {
    use HasOptions;

    case Draft = 'draft';
    case Active = 'active';
    case Superseded = 'superseded';

    public function label(): string {
        return (string) __('enums.club.grading-version-status.' . $this->value);
    }
}
