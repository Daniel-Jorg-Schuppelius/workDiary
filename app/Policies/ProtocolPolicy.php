<?php
/*
 * Created on   : Sun May 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Enums\Protocol\ProtocolStatus;
use App\Enums\User\Permission as P;
use App\Models\Protocol;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

class ProtocolPolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        unset($user);

        return true;
    }

    public function view(User $user, Protocol $protocol): bool {
        return $this->isParticipant($user, $protocol)
            || $user->can(P::ProtocolViewAny->value);
    }

    public function create(User $user): bool {
        unset($user);

        return true;
    }

    public function update(User $user, Protocol $protocol): bool {
        if (! $protocol->status->isEditable()) {
            return false;
        }

        return $this->isParticipant($user, $protocol)
            || $user->can(P::ProtocolEditDraft->value);
    }

    public function delete(User $user, Protocol $protocol): bool {
        if (! $protocol->status->isEditable()) {
            return false;
        }

        return $this->isParticipant($user, $protocol)
            || $user->can(P::ProtocolDelete->value);
    }

    public function requestReview(User $user, Protocol $protocol): bool {
        return $protocol->status === ProtocolStatus::Draft
            && ($this->isParticipant($user, $protocol) || $user->can(P::ProtocolRequestReview->value));
    }

    public function sign(User $user, Protocol $protocol): bool {
        if (! in_array($protocol->status, [ProtocolStatus::Draft, ProtocolStatus::InReview], true)) {
            return false;
        }

        return $user->can(P::ProtocolSignInternal->value)
            || $user->can(P::ProtocolSignCustomer->value);
    }

    public function archive(User $user, Protocol $protocol): bool {
        return in_array($protocol->status, [ProtocolStatus::Signed, ProtocolStatus::Draft], true)
            && $user->can(P::ProtocolArchive->value);
    }

    public function supersede(User $user, Protocol $protocol): bool {
        return $protocol->status === ProtocolStatus::Signed
            && $user->can(P::ProtocolSupersede->value);
    }

    private function isParticipant(User $user, Protocol $protocol): bool {
        return (int) $protocol->created_by_user_id === (int) $user->id;
    }
}
