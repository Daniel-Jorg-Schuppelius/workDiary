<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PersonnelFileSubmissionStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Hr;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\{HasLabel, HasStatusTransitions};

/** Einreichung zur Personalakte (MVP-987): die Personalabteilung übernimmt oder lehnt ab. */
enum PersonnelFileSubmissionStatus: string implements HasLabel, HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Submitted = 'submitted';
    case Accepted = 'accepted';
    case Rejected = 'rejected';

    public function label(): string {
        return (string) __('enums.hr_submission_status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Submitted => 'warning',
            self::Accepted => 'success',
            self::Rejected => 'error',
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Submitted => [self::Accepted, self::Rejected],
            self::Accepted, self::Rejected => [],
        };
    }
}
