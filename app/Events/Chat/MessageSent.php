<?php
/*
 * Created on   : Sat Jun 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MessageSent.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Events\Chat;

use App\Models\Chat\Message;
use Illuminate\Broadcasting\{InteractsWithSockets, PrivateChannel};
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Neue Nachricht (oder Thread-Antwort) in einem Kanal. Der Client lädt die
 * betroffene Nachricht inkrementell nach (eine Render-Quelle: Server).
 */
class MessageSent implements ShouldBroadcast {
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array {
        return [new PrivateChannel('chat.channel.' . $this->message->channel?->sqid)];
    }

    public function broadcastAs(): string {
        return 'message.sent';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array {
        return [
            'id' => $this->message->id,
            'channel_id' => $this->message->channel_id,
            'parent_id' => $this->message->parent_id,
            'user_id' => $this->message->user_id,
            'type' => $this->message->type,
        ];
    }
}
