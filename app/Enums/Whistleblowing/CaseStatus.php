<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CaseStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Whistleblowing;

/**
 * Lebenszyklus eines Hinweisgeberfalls (Abschnitt 8 des Konzepts). Wechsel
 * erfolgen ausschliesslich ueber den WhistleblowingCaseWorkflowService.
 */
enum CaseStatus: string {
    case Submitted = 'submitted';
    case Acknowledged = 'acknowledged';
    case Triage = 'triage';
    case Investigating = 'investigating';
    case WaitingReporter = 'waiting_reporter';
    case Referred = 'referred';
    case ClosedSubstantiated = 'closed_substantiated';
    case ClosedUnsubstantiated = 'closed_unsubstantiated';
    case ClosedOutOfScope = 'closed_out_of_scope';
    case ClosedDuplicate = 'closed_duplicate';
    case RetentionReview = 'retention_review';
    case LegalHold = 'legal_hold';
    case Deleted = 'deleted';

    /**
     * Grober, reporter-tauglicher Status (Abschnitt 7.2: nur „grober Status").
     * Gibt KEINE internen Details preis.
     */
    public function reporterStatus(): string {
        return match ($this) {
            self::Submitted, self::Acknowledged, self::Triage => 'received',
            self::WaitingReporter => 'awaiting_you',
            self::Investigating, self::Referred => 'in_progress',
            default => 'closed',
        };
    }

    /** Ist dies ein fachlicher Abschlusszustand (verlangt Begründung)? */
    public function isClosed(): bool {
        return in_array($this, [
            self::ClosedSubstantiated,
            self::ClosedUnsubstantiated,
            self::ClosedOutOfScope,
            self::ClosedDuplicate,
        ], true);
    }
}
