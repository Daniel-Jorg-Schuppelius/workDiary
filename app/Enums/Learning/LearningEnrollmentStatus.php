<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningEnrollmentStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Learning;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Zustand einer Einschreibung (Feature 149). Jeder Wechsel erzeugt ein
 * Ereignis mit Auslöser und Grund — der Verlauf ist Teil des Nachweises.
 */
enum LearningEnrollmentStatus: string implements HasLabel {
    use HasOptions;

    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Failed = 'failed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string {
        return (string) __('enums.learning.enrollment-status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Assigned => 'ghost',
            self::InProgress => 'info',
            self::Completed => 'success',
            self::Failed => 'error',
            self::Expired => 'warning',
            self::Cancelled => 'neutral',
        };
    }

    /** Terminal: kein weiterer Fortschritt möglich. */
    public function isFinal(): bool {
        return in_array($this, [self::Completed, self::Failed, self::Expired, self::Cancelled], true);
    }
}
