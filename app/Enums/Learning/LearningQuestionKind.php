<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningQuestionKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Learning;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Fragetyp einer Prüfung (Feature 149, MVP-738).
 *
 * Alle Typen außer `Essay` werden automatisch bewertet — der Aufsatz
 * braucht einen Menschen (die KI bewertet nichts, EU-KI-VO Anhang III
 * Nr. 3, siehe Konzept Abschnitt 19).
 */
enum LearningQuestionKind: string implements HasLabel {
    use HasOptions;

    case Single = 'single';
    case Multiple = 'multiple';
    case TrueFalse = 'true_false';
    case ShortText = 'short_text';
    case Cloze = 'cloze';
    case Sort = 'sort';
    case Matching = 'matching';
    // Bildmarkierung: Klick auf einen Bildbereich (MVP-738, Zusatztyp).
    case Hotspot = 'hotspot';
    // Matrix: Zeilen einer gemeinsamen Spaltenmenge zuordnen — anders als
    // `Matching` darf eine Spalte mehrfach vorkommen.
    case Matrix = 'matrix';
    case Essay = 'essay';

    public function label(): string {
        return (string) __('enums.learning.question-kind.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Single, self::TrueFalse => 'info',
            self::Multiple => 'success',
            self::ShortText, self::Cloze => 'warning',
            self::Sort, self::Matching => 'neutral',
            self::Hotspot, self::Matrix => 'info',
            self::Essay => 'error',
        };
    }

    /** Automatisch bewertbar? Der Aufsatz braucht eine Bewertung durch Menschen. */
    public function isAutoGradable(): bool {
        return $this !== self::Essay;
    }

    /** Braucht der Typ Antwortoptionen? */
    public function needsOptions(): bool {
        return in_array($this, [self::Single, self::Multiple, self::TrueFalse, self::Sort, self::Matching], true);
    }

    /** Braucht der Typ ein Bild? */
    public function needsImage(): bool {
        return $this === self::Hotspot;
    }
}
