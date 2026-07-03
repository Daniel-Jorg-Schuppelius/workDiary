<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MonthClosureStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\TimeApproval;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Status einer Monatsfreigabe (MVP-016).
 * Siehe ../WorkDiary-Architecture/monatsfreigabe.md §4 für die Übergänge.
 */
enum MonthClosureStatus: string implements HasLabel {
    use HasOptions;

    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Reopened = 'reopened';
    case Locked = 'locked';

    public function label(): string {
        return match ($this) {
            self::Draft     => __('Entwurf'),
            self::Submitted => __('Eingereicht'),
            self::Approved  => __('Genehmigt'),
            self::Rejected  => __('Abgelehnt'),
            self::Reopened  => __('Wiedereröffnet'),
            self::Locked    => __('Gesperrt'),
        };
    }

    public function tone(): string {
        return match ($this) {
            self::Draft     => 'ghost',
            self::Submitted => 'info',
            self::Approved  => 'success',
            self::Rejected  => 'error',
            self::Reopened  => 'warning',
            self::Locked    => 'secondary',
        };
    }

    /**
     * Stati, in denen die zugehörigen Anwesenheits-/Zeitdaten faktisch
     * gesperrt sind (jede direkte Bearbeitung verlangt Korrekturantrag
     * bzw. vorhergehendes Reopen).
     *
     * @return list<self>
     */
    public static function lockedStates(): array {
        return [self::Submitted, self::Approved, self::Locked];
    }

    public function isLocked(): bool {
        return in_array($this, self::lockedStates(), true);
    }

    public function isTerminal(): bool {
        return $this === self::Locked;
    }
}
