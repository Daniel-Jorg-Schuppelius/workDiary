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

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\HasStatusTransitions;

/**
 * Status einer Monatsfreigabe (MVP-016).
 * Siehe ../WorkDiary-Architecture/monatsfreigabe.md §4 für die Übergänge.
 */
enum MonthClosureStatus: string implements HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Reopened = 'reopened';
    case Locked = 'locked';

    public function label(): string {
        return match ($this) {
            self::Draft     => __('enums.time_approval.month_closure_status.draft'),
            self::Submitted => __('enums.time_approval.month_closure_status.submitted'),
            self::Approved  => __('enums.time_approval.month_closure_status.approved'),
            self::Rejected  => __('enums.time_approval.month_closure_status.rejected'),
            self::Reopened  => __('enums.time_approval.month_closure_status.reopened'),
            self::Locked    => __('enums.time_approval.month_closure_status.locked'),
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

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Draft, self::Reopened => [self::Submitted],
            self::Submitted => [self::Approved, self::Rejected],
            self::Approved => [self::Reopened, self::Locked],
            // Abgelehnt: neu einreichen, selbst zurück in den Entwurf oder durch die Prüfung wieder öffnen.
            self::Rejected => [self::Submitted, self::Draft, self::Reopened],
            self::Locked => [self::Reopened],
        };
    }
}
