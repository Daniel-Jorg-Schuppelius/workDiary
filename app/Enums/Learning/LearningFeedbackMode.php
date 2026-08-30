<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningFeedbackMode.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Learning;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Wann eine Prüfung ihre Rückmeldung zeigt (Feature 149, MVP-738).
 * `None` ist für Wiederholungsprüfungen gedacht, bei denen die Lösung
 * nicht durchsickern soll.
 */
enum LearningFeedbackMode: string implements HasLabel {
    use HasOptions;

    case Immediate = 'immediate';
    case End = 'end';
    case None = 'none';

    public function label(): string {
        return (string) __('enums.learning.feedback-mode.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Immediate => 'success',
            self::End => 'info',
            self::None => 'ghost',
        };
    }
}
