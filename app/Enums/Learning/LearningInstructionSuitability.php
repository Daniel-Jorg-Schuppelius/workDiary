<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningInstructionSuitability.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Learning;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Unterweisungstauglichkeit (Feature 149): Die Unfallversicherungsträger
 * akzeptieren rein digitale Unterweisung überwiegend nur ergänzend — der
 * Nachweis führt die Einstufung deshalb mit, damit die Software nicht den
 * Eindruck erzeugt, ein Klick-Durchlauf erfülle § 12 ArbSchG.
 */
enum LearningInstructionSuitability: string implements HasLabel {
    use HasOptions;

    case Supplementary = 'supplementary';
    case WithQuestions = 'with_questions';
    case WithPresence = 'with_presence';

    public function label(): string {
        return (string) __('enums.learning.instruction-suitability.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Supplementary => 'ghost',
            self::WithQuestions => 'info',
            self::WithPresence => 'success',
        };
    }
}
