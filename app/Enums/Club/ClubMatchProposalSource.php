<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubMatchProposalSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Club;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Herkunft eines importierten Spielplan-Vorschlags. */
enum ClubMatchProposalSource: string implements HasLabel {
    use HasOptions;

    case Csv = 'csv';
    case Ics = 'ics';

    public function label(): string {
        return (string) __('enums.club.match-proposal-source.' . $this->value);
    }
}
