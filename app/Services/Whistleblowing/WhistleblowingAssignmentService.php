<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WhistleblowingAssignmentService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Whistleblowing;

use App\Enums\Whistleblowing\CaseRole;
use App\Models\User;
use App\Models\Whistleblowing\{CaseAssignment, WhistleblowingCase};
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Verwaltet die Bearbeiterliste eines Falls (Abschnitt 7.4). Massgeblich fuer die
 * Autorisierung ({@see \App\Policies\WhistleblowingCasePolicy}). Prueft die
 * Mandantengrenze und einfache Interessenkonflikte.
 */
class WhistleblowingAssignmentService {
    public function __construct(private readonly WhistleblowingEventService $events) {}

    public function assign(WhistleblowingCase $case, User $user, CaseRole $role, User $assignedBy): CaseAssignment {
        if ((int) $user->organization_id !== (int) $case->getAttribute('organization_id')) {
            throw new RuntimeException('Bearbeiter gehoert nicht zur Organisation des Falls.');
        }

        // §7.4: gesperrte Person (Interessenkonflikt oder benannter Betroffener)
        // darf nicht zugewiesen werden.
        if ($case->isBlockedFor($user)) {
            throw new RuntimeException('Person ist fuer diesen Fall gesperrt (Interessenkonflikt oder Betroffener).');
        }

        // Bereits aktiv zugewiesen → idempotent.
        $existing = $case->assignments()
            ->where('user_id', $user->getKey())
            ->where('role', $role->value)
            ->whereNull('revoked_at')
            ->first();
        if ($existing instanceof CaseAssignment) {
            return $existing;
        }

        $assignment = CaseAssignment::create([
            'organization_id' => $case->getAttribute('organization_id'),
            'case_id' => $case->getKey(),
            'user_id' => $user->getKey(),
            'role' => $role->value,
            'assigned_by' => $assignedBy->getKey(),
            'assigned_at' => Carbon::now(),
        ]);

        $this->events->record($case, WhistleblowingEventService::CASE_ASSIGNED, $assignedBy, [
            'assignee_user_id' => (int) $user->getKey(),
            'role' => $role->value,
        ]);

        return $assignment;
    }

    public function revoke(WhistleblowingCase $case, User $user, CaseRole $role, User $actor): void {
        $assignment = $case->assignments()
            ->where('user_id', $user->getKey())
            ->where('role', $role->value)
            ->whereNull('revoked_at')
            ->first();

        if ($assignment instanceof CaseAssignment) {
            $assignment->forceFill(['revoked_at' => Carbon::now()])->save();
            $this->events->record($case, WhistleblowingEventService::CASE_ASSIGNED, $actor, [
                'assignee_user_id' => (int) $user->getKey(),
                'role' => $role->value,
                'revoked' => true,
            ]);
        }
    }
}
