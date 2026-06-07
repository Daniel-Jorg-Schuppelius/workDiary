<?php
/*
 * Created on   : Sun Jun 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ChannelRead.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Events\Chat;

use Illuminate\Broadcasting\{InteractsWithSockets, PrivateChannel};
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Ein Mitglied hat den Kanal bis $readTs gelesen (für Lesebestätigungen ✓✓). */
class ChannelRead implements ShouldBroadcast {
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public string $channelId, public int $userId, public int $readTs) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array {
        return [new PrivateChannel('chat.channel.' . $this->channelId)];
    }

    public function broadcastAs(): string {
        return 'channel.read';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array {
        return ['channel_id' => $this->channelId, 'user_id' => $this->userId, 'read_ts' => $this->readTs];
    }
}
