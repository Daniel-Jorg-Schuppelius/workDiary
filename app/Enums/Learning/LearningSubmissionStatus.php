<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningSubmissionStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Learning;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Zustand einer Aufgaben-Abgabe (Feature 149, MVP-739).
 *
 * `Returned` ist die Rückgabe zur Überarbeitung — sie ist kein Scheitern,
 * sondern ein Arbeitsschritt: die Person darf erneut abgeben.
 */
enum LearningSubmissionStatus: string implements HasLabel {
    use HasOptions;

    case Draft = 'draft';
    case Submitted = 'submitted';
    case Returned = 'returned';
    case Graded = 'graded';

    public function label(): string {
        return (string) __('enums.learning.submission-status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Draft => 'ghost',
            self::Submitted => 'info',
            self::Returned => 'warning',
            self::Graded => 'success',
        };
    }

    /** Darf die lernende Person (erneut) abgeben? */
    public function allowsSubmission(): bool {
        return $this === self::Draft || $this === self::Returned;
    }
}
