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

use App\Models\User;
use App\Models\Whistleblowing\{CaseEvent, WhistleblowingCase};

/**
 * Schreibt minimierte, append-only Fall-Ereignisse in die Hash-Kette
 * (whistleblowing_case_events). Enthaelt NIE Meldeinhalte, Reporter-IP oder
 * User-Agent – nur fachliche Eventcodes und unkritische Metadaten.
 */
class WhistleblowingEventService {
    // Fachliche Eventcodes (Abschnitt 9.6).
    public const CASE_SUBMITTED = 'case.submitted';
    public const CASE_VIEWED = 'case.viewed';
    public const CASE_ASSIGNED = 'case.assigned';
    public const CASE_ACKNOWLEDGED = 'case.acknowledged';
    public const CASE_STATUS_CHANGED = 'case.status_changed';
    public const MESSAGE_SENT_TO_REPORTER = 'message.sent_to_reporter';
    public const MESSAGE_FROM_REPORTER = 'message.from_reporter';
    public const ATTACHMENT_UPLOADED = 'attachment.uploaded';
    public const ATTACHMENT_REJECTED = 'attachment.rejected';
    public const CASE_EXPORTED = 'case.exported';
    public const CASE_LEGAL_HOLD_SET = 'case.legal_hold_set';
    public const CASE_DELETED = 'case.deleted';
    public const EMERGENCY_ACCESS_GRANTED = 'emergency_access.granted';
    public const CASE_CONFLICT_DECLARED = 'case.conflict_declared';
    public const CASE_SUBJECT_ADDED = 'case.subject_added';

    /**
     * @param array<string, scalar|null> $metadata minimiert, ohne Meldeinhalte
     */
    public function record(
        WhistleblowingCase $case,
        string $event,
        ?User $actor = null,
        array $metadata = [],
    ): CaseEvent {
        return CaseEvent::create([
            'organization_id' => $case->getAttribute('organization_id'),
            'case_id' => $case->getKey(),
            'actor_type' => $actor instanceof User ? 'user' : 'system',
            'actor_user_id' => $actor?->getKey(),
            'event' => $event,
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }

    /**
     * Portal-/fall-lose Ereignisse (case_id null), z. B. Portalaufrufe.
     *
     * @param array<string, scalar|null> $metadata
     */
    public function recordSystem(?int $organizationId, string $event, array $metadata = []): CaseEvent {
        return CaseEvent::create([
            'organization_id' => $organizationId,
            'case_id' => null,
            'actor_type' => 'system',
            'actor_user_id' => null,
            'event' => $event,
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }
}
