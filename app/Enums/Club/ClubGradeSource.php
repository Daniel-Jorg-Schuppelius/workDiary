<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubGradeSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Club;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Herkunft eines Grades (MVP-846/847): bestandene Prüfung oder belegte Anerkennung (Vereinswechsel). */
enum ClubGradeSource: string implements HasLabel {
    use HasOptions;

    case Exam = 'exam';
    case Recognized = 'recognized';

    public function label(): string {
        return (string) __('enums.club.grade-source.' . $this->value);
    }
}
