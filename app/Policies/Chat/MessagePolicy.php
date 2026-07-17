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

use App\Models\Chat\{Channel, Message};
use App\Models\User;
use App\Policies\Concerns\ChecksOwnership;

class MessagePolicy {
    use ChecksOwnership;

    // KEIN pauschaler Admin-Bypass: in privaten Kanälen/DMs haben Admins ohne
    // Mitgliedschaft keinen Zugriff. Inhalte bearbeiten darf nur der Autor selbst;
    // Löschen (Moderation) zusätzlich Admins in ÖFFENTLICHEN Kanälen.

    /** Eigene Nachricht bearbeiten (auch Admins nicht fremde Inhalte). */
    public function update(User $user, Message $message): bool {
        return $this->owns($user, $message);
    }

    /** Eigene Nachricht, Kanal-Eigentümer, oder Admin in öffentlichem Kanal. */
    public function delete(User $user, Message $message): bool {
        $channel = $message->channel;

        return $this->owns($user, $message)
            || ($channel?->isOwner($user) ?? false)
            || ($channel instanceof Channel
                && $user->isAdmin()
                && $channel->type === 'channel'
                && $channel->visibility === 'public');
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
