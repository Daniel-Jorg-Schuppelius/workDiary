<?php
/*
 * Created on   : Sun Jun 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SendScheduledMessages.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands;

use App\Events\Chat\MessageSent;
use App\Models\Chat\Channel;
use App\Models\Chat\ScheduledMessage;
use App\Services\PushNotifier;
use Illuminate\Console\Command;

/**
 * Erzeugt und versendet fällige geplante Chat-Nachrichten. Die echte Nachricht
 * entsteht erst hier (korrekte Reihenfolge/Echtzeit). Läuft minütlich.
 */
class SendScheduledMessages extends Command {
    protected $signature = 'chat:send-scheduled';

    protected $description = 'Sendet fällige geplante Chat-Nachrichten.';

    public function handle(PushNotifier $push): int {
        $due = ScheduledMessage::query()
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->limit(200)
            ->get();

        $count = 0;
        foreach ($due as $sched) {
            // Ohne Org-Kontext (Cron): Channel ohne globalen Scope laden, org_id explizit setzen.
            $channel = Channel::withoutGlobalScopes()->find($sched->channel_id);
            if ($channel === null) {
                $sched->delete();
                continue;
            }

            $message = $channel->messages()->create([
                'organization_id' => $channel->organization_id,
                'user_id' => $sched->user_id,
                'body' => $sched->body,
                'type' => 'text',
            ]);

            broadcast(new MessageSent($message));
            $channel->members()->where('users.id', '!=', $sched->user_id)->pluck('users.id')
                ->each(fn ($id) => broadcast(new \App\Events\Chat\ChannelListChanged((int) $id)));
            $push->chatMessage($message);
            $sched->delete();
            $count++;
        }

        if ($count > 0) {
            $this->info("{$count} geplante Nachricht(en) gesendet.");
        }

        return self::SUCCESS;
    }
}
