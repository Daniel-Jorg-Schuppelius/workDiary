<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubProofKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Club;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Nachweisart (MVP-846): Pflichtlehrgang oder externer Trainingsnachweis mit Herkunft. */
enum ClubProofKind: string implements HasLabel {
    use HasOptions;

    case Course = 'course';
    case ExternalTraining = 'external_training';

    public function label(): string {
        return (string) __('enums.club.proof-kind.' . $this->value);
    }
}
