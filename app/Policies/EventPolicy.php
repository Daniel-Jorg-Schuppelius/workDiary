<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EventPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Enums\User\UserRole;
use App\Models\{Event, User};
use App\Policies\Concerns\{ChecksOwnership, HasAdminBypass};

class EventPolicy {
    use ChecksOwnership;
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return true;
    }

    public function view(User $user, Event $event): bool {
        // Verantwortlicher, Teilnehmer, oder TrainingManager dürfen sehen.
        if ($this->isResponsibleOrParticipant($user, $event)) {
            return true;
        }

        return $this->isTrainingManager($user);
    }

    public function create(User $user): bool {
        return $this->isTrainingManager($user);
    }

    public function update(User $user, Event $event): bool {
        return $this->isResponsible($user, $event) || $this->isTrainingManager($user);
    }

    public function delete(User $user, Event $event): bool {
        return $this->isResponsible($user, $event) || $this->isTrainingManager($user);
    }

    public function cancel(User $user, Event $event): bool {
        return $this->update($user, $event);
    }

    /** Pflichtschulungs-Flag setzen ist eingeschränkt. */
    public function manageMandatory(User $user): bool {
        return $this->isTrainingManager($user);
    }

    public function manageParticipants(User $user, Event $event): bool {
        return $this->update($user, $event);
    }

    public function issueCertificate(User $user, Event $event): bool {
        return $this->update($user, $event);
    }

    private function isResponsible(User $user, Event $event): bool {
        return (int) $user->id === (int) $event->responsible_user_id;
    }

    private function isResponsibleOrParticipant(User $user, Event $event): bool {
        if ($this->isResponsible($user, $event)) {
            return true;
        }

        return $event->participants()->where('users.id', $user->getKey())->exists();
    }

    private function isTrainingManager(User $user): bool {
        return $user->hasRole(UserRole::TrainingManager->value);
    }
}
