<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningProgressStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Learning;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Fortschritt je Lerneinheit (Feature 149).
 */
enum LearningProgressStatus: string implements HasLabel {
    use HasOptions;

    case Open = 'open';
    case Started = 'started';
    case Completed = 'completed';

    public function label(): string {
        return (string) __('enums.learning.progress-status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Open => 'ghost',
            self::Started => 'info',
            self::Completed => 'success',
        };
    }
}
