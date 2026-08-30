<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningCourseStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Learning;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Lebenszyklus eines Lernkurses (Feature 149). Die Freigabe friert den
 * Inhaltsbaum als Version ein; danach ist der Inhalt gesperrt und eine
 * Korrektur läuft über eine Folgeversion.
 */
enum LearningCourseStatus: string implements HasLabel {
    use HasOptions;

    case Draft = 'draft';
    case Review = 'review';
    case Released = 'released';
    case Archived = 'archived';

    public function label(): string {
        return (string) __('enums.learning.course-status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Draft => 'ghost',
            self::Review => 'warning',
            self::Released => 'success',
            self::Archived => 'neutral',
        };
    }

    /** Inhalt bearbeitbar? Freigegebene und archivierte Kurse sind gesperrt. */
    public function isEditable(): bool {
        return $this === self::Draft || $this === self::Review;
    }
}
