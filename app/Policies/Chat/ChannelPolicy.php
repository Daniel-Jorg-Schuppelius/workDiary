<?php
/*
 * Created on   : Sat Jun 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ChannelPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies\Chat;

use App\Models\Chat\Channel;
use App\Models\User;

class ChannelPolicy {
    // KEIN pauschaler Admin-Bypass: private Kanäle und Direktnachrichten bleiben
    // auch für Plattform-Admins vertraulich (Zugriff nur als Mitglied). Admins
    // dürfen lediglich ÖFFENTLICHE Kanäle moderieren (adminMayModerate()).

    public function viewAny(User $user): bool {
        return true;
    }

    /** Admin-Moderation ausschließlich in öffentlichen Kanälen (nie privat/DM). */
    private function adminMayModerate(User $user, Channel $channel): bool {
        return $user->isAdmin()
            && $channel->type === 'channel'
            && $channel->visibility === 'public';
    }

    /** Sichtbar für Mitglieder; öffentliche Kanäle für alle der Organisation. */
    public function view(User $user, Channel $channel): bool {
        return $channel->hasMember($user)
            || ($channel->type === 'channel' && $channel->visibility === 'public');
    }

    public function create(User $user): bool {
        return true;
    }

    /** Posten: Mitglied eines nicht archivierten Kanals. */
    public function post(User $user, Channel $channel): bool {
        return ! $channel->is_archived && $channel->hasMember($user);
    }

    /** Beitreten: nur öffentliche Kanäle frei; private per Einladung (manageMembers). */
    public function join(User $user, Channel $channel): bool {
        return $channel->type === 'channel'
            && $channel->visibility === 'public'
            && ! $channel->is_archived;
    }

    public function update(User $user, Channel $channel): bool {
        return $channel->isOwner($user) || $this->adminMayModerate($user, $channel);
    }

    public function delete(User $user, Channel $channel): bool {
        return $channel->isOwner($user) || $this->adminMayModerate($user, $channel);
    }

    /** Mitglieder verwalten/einladen: Eigentümer (oder Admin in öffentlichem Kanal). */
    public function manageMembers(User $user, Channel $channel): bool {
        return $channel->isOwner($user) || $this->adminMayModerate($user, $channel);
    }
}
