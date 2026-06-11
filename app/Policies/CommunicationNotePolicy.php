<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CommunicationNotePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Enums\User\Permission as P;
use App\Models\{CommunicationNote, User};
use App\Policies\Concerns\HasAdminBypass;

/**
 * Zugriffsregeln für Kommunikationsnotizen (MVP-012, docs/kommunikationsnotizen.md §7):
 * - vertrauliche Notizen sehen nur Erfasser + Inhaber von communication.confidential.manage
 *   (Org-Admin via HasAdminBypass),
 * - bearbeiten darf der Erfasser < 24 h nach Anlage, danach nur der Org-Admin.
 */
class CommunicationNotePolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::CommunicationViewAny->value);
    }

    public function view(User $user, CommunicationNote $note): bool {
        if (! $this->canSeeConfidential($user, $note)) {
            return false;
        }

        return (int) $note->created_by_user_id === (int) $user->id
            || $user->can(P::CommunicationView->value);
    }

    public function create(User $user): bool {
        return $user->can(P::CommunicationCreate->value);
    }

    public function update(User $user, CommunicationNote $note): bool {
        if (! $this->canSeeConfidential($user, $note)) {
            return false;
        }

        // Erfasser darf nur innerhalb von 24 h nach Anlage bearbeiten;
        // danach bleibt das dem Org-Admin (before()-Bypass) vorbehalten.
        return $user->can(P::CommunicationUpdate->value)
            && (int) $note->created_by_user_id === (int) $user->id
            && $note->created_at !== null
            && $note->created_at->gt(now()->subDay());
    }

    public function delete(User $user, CommunicationNote $note): bool {
        return $this->canSeeConfidential($user, $note)
            && $user->can(P::CommunicationDelete->value);
    }

    public function publishToCustomer(User $user): bool {
        return $user->can(P::CommunicationPublishToCustomer->value);
    }

    public function manageConfidential(User $user): bool {
        return $user->can(P::CommunicationConfidentialManage->value);
    }

    /**
     * Folgeaktion erledigen: Erfasser oder Verantwortlicher der Folgeaktion
     * (Org-Admin via before()-Bypass).
     */
    public function completeFollowup(User $user, CommunicationNote $note): bool {
        if (! $this->canSeeConfidential($user, $note)) {
            return false;
        }

        return $user->can(P::CommunicationUpdate->value)
            && ((int) $note->created_by_user_id === (int) $user->id
                || (int) $note->next_action_user_id === (int) $user->id);
    }

    private function canSeeConfidential(User $user, CommunicationNote $note): bool {
        if (! $note->confidential) {
            return true;
        }

        return (int) $note->created_by_user_id === (int) $user->id
            || $user->can(P::CommunicationConfidentialManage->value);
    }
}
