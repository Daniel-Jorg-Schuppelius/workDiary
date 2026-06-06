<?php
/*
 * Created on   : Sat Jun 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MessagePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies\Chat;

use App\Models\Chat\Message;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

class MessagePolicy {
    use HasAdminBypass;

    /** Eigene Nachricht bearbeiten. */
    public function update(User $user, Message $message): bool {
        return $message->user_id === $user->id;
    }

    /** Eigene Nachricht oder als Kanal-Eigentümer löschen. */
    public function delete(User $user, Message $message): bool {
        return $message->user_id === $user->id
            || ($message->channel?->isOwner($user) ?? false);
    }

    /** Anpinnen: jedes Kanal-Mitglied. */
    public function pin(User $user, Message $message): bool {
        return $message->channel?->hasMember($user) ?? false;
    }

    /** Reagieren: jedes Kanal-Mitglied. */
    public function react(User $user, Message $message): bool {
        return $message->channel?->hasMember($user) ?? false;
    }
}
