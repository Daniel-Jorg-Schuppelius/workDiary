<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FormSubmissionStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Form;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\HasStatusTransitions;

/**
 * Status einer Formulareinsendung (Feature 203, MVP-1650): Ein Entwurf wird
 * je Feld gespeichert und erst mit „Absenden“ zum Nachweis; abgesendet ist
 * endgültig. Bestandseinsendungen gelten als abgesendet.
 */
enum FormSubmissionStatus: string implements HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Draft = 'draft';
    case Submitted = 'submitted';

    public function label(): string {
        return (string) __('enums.form.submission_status.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::Draft => 'warning',
            self::Submitted => 'success',
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Draft => [self::Submitted],
            self::Submitted => [],
        };
    }
}
