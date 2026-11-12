<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WhistleblowingEventService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Whistleblowing;

use App\Models\Platform\User;
use App\Models\Whistleblowing\{CaseEvent, WhistleblowingCase};

/**
 * Schreibt minimierte, append-only Fall-Ereignisse in die Hash-Kette
 * (whistleblowing_case_events). Enthaelt NIE Meldeinhalte, Reporter-IP oder
 * User-Agent – nur fachliche Eventcodes und unkritische Metadaten.
 */
class WhistleblowingEventService {
    // Fachliche Eventcodes (Abschnitt 9.6).
    public const string CASE_SUBMITTED = 'case.submitted';
    public const string CASE_VIEWED = 'case.viewed';
    public const string CASE_ASSIGNED = 'case.assigned';
    public const string CASE_ACKNOWLEDGED = 'case.acknowledged';
    public const string CASE_STATUS_CHANGED = 'case.status_changed';
    public const string MESSAGE_SENT_TO_REPORTER = 'message.sent_to_reporter';
    public const string MESSAGE_FROM_REPORTER = 'message.from_reporter';
    public const string ATTACHMENT_UPLOADED = 'attachment.uploaded';
    public const string ATTACHMENT_REJECTED = 'attachment.rejected';
    public const string CASE_EXPORTED = 'case.exported';
    public const string CASE_LEGAL_HOLD_SET = 'case.legal_hold_set';
    public const string CASE_DELETED = 'case.deleted';
    public const string EMERGENCY_ACCESS_GRANTED = 'emergency_access.granted';
    public const string CASE_CONFLICT_DECLARED = 'case.conflict_declared';
    public const string CASE_SUBJECT_ADDED = 'case.subject_added';
    public const string CASE_ASSIGNMENT_REVOKED = 'case.assignment_revoked';
    public const string CASE_FEEDBACK_SENT = 'case.feedback_sent';
    public const string CASE_REOPENED = 'case.reopened';
    public const string CASE_AUDIT_VIEWED = 'case.audit_viewed';

    /**
     * @param array<string, scalar|null> $metadata minimiert, ohne Meldeinhalte
     */
    public function record(
        WhistleblowingCase $case,
        string $event,
        ?User $actor = null,
        array $metadata = [],
    ): CaseEvent {
        /** @var CaseEvent $entry */
        $entry = $case->record($event, $metadata, $actor, extra: ['actor_type' => $actor instanceof User ? 'user' : 'system']);

        return $entry;
    }

    /**
     * Portal-/fall-lose Ereignisse (case_id null), z. B. Portalaufrufe.
     *
     * @param array<string, scalar|null> $metadata
     */
    public function recordSystem(?int $organizationId, string $event, array $metadata = []): CaseEvent {
        return CaseEvent::log(null, $event, $metadata, null, extra: ['organization_id' => $organizationId, 'actor_type' => 'system']);
    }
}
