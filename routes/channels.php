<?php
/*
 * Created on   : Sat Jun 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : channels.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Models\Chat\Channel;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function (User $user, int $id) {
    return $user->id === $id;
});

// Privater Chat-Kanal: nur Mitglieder dürfen lauschen. Die SetOrganizationContext-
// Middleware bindet currentOrganization, daher greift der OrganizationScope auf
// Channel und verhindert mandantenübergreifenden Zugriff.
// Channelname ist die Channel-Sqid (kein roher PK in URLs/Sockets). Auflösung
// per resolveRouteBinding ist org-gescopt → keine kanal-/mandantenübergreifende Auth.
Broadcast::channel('chat.channel.{channelSqid}', function (User $user, string $channelSqid): bool {
    $channel = (new Channel)->resolveRouteBinding($channelSqid);

    return $channel instanceof Channel && $channel->hasMember($user);
});

// Präsenz-Channel: liefert Nutzerinfos der online anwesenden Mitglieder.
// Rückgabe-Array (statt bool) = autorisiert + diese Daten werden geteilt.
Broadcast::channel('presence-chat.channel.{channelSqid}', function (User $user, string $channelSqid): ?array {
    $channel = (new Channel)->resolveRouteBinding($channelSqid);

    return ($channel instanceof Channel && $channel->hasMember($user))
        ? ['id' => $user->id, 'name' => $user->name]
        : null;
});
