<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DataSubjectRequestStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Privacy;

/** Bearbeitungsstand einer Betroffenenanfrage. */
enum DataSubjectRequestStatus: string {
    case Intake = 'intake';                 // eingegangen, noch nicht geprüft
    case IdentityCheck = 'identity_check';  // Identitätsprüfung läuft
    case InProgress = 'in_progress';        // in Bearbeitung
    case AwaitingInfo = 'awaiting_info';    // Rückfrage / Fristverlängerung
    case Completed = 'completed';           // beantwortet/erledigt
    case Rejected = 'rejected';             // abgelehnt (mit Begründung)
    case Withdrawn = 'withdrawn';           // zurückgezogen

    public function label(): string {
        return match ($this) {
            self::Intake => __('Eingegangen'),
            self::IdentityCheck => __('Identitätsprüfung'),
            self::InProgress => __('In Bearbeitung'),
            self::AwaitingInfo => __('Wartet auf Information'),
            self::Completed => __('Erledigt'),
            self::Rejected => __('Abgelehnt'),
            self::Withdrawn => __('Zurückgezogen'),
        };
    }

    /** Offene (noch fristrelevante) Stati. */
    public function isOpen(): bool {
        return ! in_array($this, [self::Completed, self::Rejected, self::Withdrawn], true);
    }
}
