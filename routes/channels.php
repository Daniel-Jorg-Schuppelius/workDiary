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
Broadcast::channel('chat.channel.{channelId}', function (User $user, int $channelId): bool {
    $channel = Channel::query()->whereKey($channelId)->first();

    return $channel instanceof Channel && $channel->hasMember($user);
});
