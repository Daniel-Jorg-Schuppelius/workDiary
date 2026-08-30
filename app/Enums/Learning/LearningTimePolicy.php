<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningTimePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Learning;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Zeitpolitik eines Kurses (Feature 149, Abschnitt 12). § 12 Abs. 1 ArbSchG
 * verlangt Unterweisungen „während ihrer Arbeitszeit" — deshalb sperrt
 * `WorkTimeRequired` den Start außerhalb der Arbeitszeit, statt ihn nur
 * hinterher zu vergüten. `VoluntaryUnpaid` ist nie Vorgabe und für Kurse
 * mit Pflichtbezug (Feature 145) gesperrt.
 */
enum LearningTimePolicy: string implements HasLabel {
    use HasOptions;

    case WorkTimeRequired = 'work_time_required';
    case AlwaysCounts = 'always_counts';
    case ApprovalRequired = 'approval_required';
    case VoluntaryUnpaid = 'voluntary_unpaid';

    public function label(): string {
        return (string) __('enums.learning.time-policy.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::WorkTimeRequired => 'warning',
            self::AlwaysCounts => 'success',
            self::ApprovalRequired => 'info',
            self::VoluntaryUnpaid => 'ghost',
        };
    }

    /** Zählt Lernzeit außerhalb der Arbeitszeit als Arbeitszeit? */
    public function countsOutsideWorkTime(): bool {
        return $this !== self::VoluntaryUnpaid;
    }

    /** Darf außerhalb der Arbeitszeit überhaupt gestartet werden? */
    public function allowsStartOutsideWorkTime(): bool {
        return $this !== self::WorkTimeRequired;
    }
}
