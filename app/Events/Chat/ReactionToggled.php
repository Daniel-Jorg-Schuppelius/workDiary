<?php
/*
 * Created on   : Sat Jun 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReactionToggled.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Events\Chat;

use Illuminate\Broadcasting\{InteractsWithSockets, PrivateChannel};
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Reaktion (Emoji/Like) an einer Nachricht hinzugefügt oder entfernt. */
class ReactionToggled implements ShouldBroadcast {
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public int $channelId, public string $messageId) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array {
        return [new PrivateChannel('chat.channel.' . $this->channelId)];
    }

    public function broadcastAs(): string {
        return 'reaction.toggled';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array {
        return ['message_id' => $this->messageId, 'channel_id' => $this->channelId];
    }
}
