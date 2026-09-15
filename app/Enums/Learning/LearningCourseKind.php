<?php
/*
 * Created on   : Tue Sep 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningCourseKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Learning;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Art eines Lernkurses (Feature 149, MVP-784): ein Kurs mit Inhalt — oder
 * eine Prüfung ohne Kurs, die einen anderen Kurs anrechnet.
 */
enum LearningCourseKind: string implements HasLabel {
    use HasOptions;

    case Course = 'course';
    case Exam = 'exam';

    public function label(): string {
        return (string) __('enums.learning.course-kind.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Course => 'ghost',
            self::Exam => 'warning',
        };
    }
}
