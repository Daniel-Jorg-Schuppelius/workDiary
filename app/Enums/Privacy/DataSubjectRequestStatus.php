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

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Bearbeitungsstand einer Betroffenenanfrage. */
enum DataSubjectRequestStatus: string implements HasLabel {
    use HasOptions;

    case Intake = 'intake';                 // eingegangen, noch nicht geprüft
    case IdentityCheck = 'identity_check';  // Identitätsprüfung läuft
    case InProgress = 'in_progress';        // in Bearbeitung
    case AwaitingInfo = 'awaiting_info';    // Rückfrage / Fristverlängerung
    case Completed = 'completed';           // beantwortet/erledigt
    case Rejected = 'rejected';             // abgelehnt (mit Begründung)
    case Withdrawn = 'withdrawn';           // zurückgezogen

    public function label(): string {
        return match ($this) {
            self::Intake => __('enums.privacy.data_subject_request_status.intake'),
            self::IdentityCheck => __('enums.privacy.data_subject_request_status.identity_check'),
            self::InProgress => __('enums.privacy.data_subject_request_status.in_progress'),
            self::AwaitingInfo => __('enums.privacy.data_subject_request_status.awaiting_info'),
            self::Completed => __('enums.privacy.data_subject_request_status.completed'),
            self::Rejected => __('enums.privacy.data_subject_request_status.rejected'),
            self::Withdrawn => __('enums.privacy.data_subject_request_status.withdrawn'),
        };
    }

    /** Offene (noch fristrelevante) Stati. */
    public function isOpen(): bool {
        return ! in_array($this, [self::Completed, self::Rejected, self::Withdrawn], true);
    }
}
