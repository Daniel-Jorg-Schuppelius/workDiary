<?php
/*
 * Created on   : Sat Aug 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningTranslationStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Learning;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Stand einer Kursübersetzung (Feature 149, MVP-748).
 *
 * **Maßgeblich bleibt die Ausgangssprache.** Eine maschinelle Übersetzung
 * einer Sicherheitsunterweisung wird Lernenden erst nach Freigabe durch
 * einen Menschen gezeigt — deshalb sind es zwei Zustände und kein Schalter.
 */
enum LearningTranslationStatus: string implements HasLabel {
    use HasOptions;

    case Draft = 'draft';
    case Approved = 'approved';

    public function label(): string {
        return (string) __('enums.learning.translation-status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Draft => 'ghost',
            self::Approved => 'success',
        };
    }

    /** Darf die Übersetzung Lernenden gezeigt werden? */
    public function isVisibleToLearners(): bool {
        return $this === self::Approved;
    }
}
