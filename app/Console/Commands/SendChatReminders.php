<?php
/*
 * Created on   : Sun Jun 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SendChatReminders.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands;

use App\Console\Concerns\IteratesModuleOrganizations;
use App\Models\Chat\{Channel, Reminder};
use App\Modules\Attributes\BelongsToModule;
use App\Services\Chat\ChatNotificationService;
use Illuminate\Console\Command;

/**
 * Verschickt fällige Chat-Erinnerungen (remind_at erreicht) über den
 * zentralen NotificationDispatcher (B7) und markiert sie als versendet.
 * Läuft minütlich über den Scheduler.
 */
#[BelongsToModule('chat')]
class SendChatReminders extends Command {
    use IteratesModuleOrganizations;

    protected $signature = 'chat:send-reminders';

    protected $description = 'Verschickt fällige Chat-Erinnerungen.';

    public function handle(ChatNotificationService $chat): int {
        $due = Reminder::query()
            ->whereNull('sent_at')
            ->where('remind_at', '<=', now())
            ->whereIn('channel_id', $this->whereModuleActive(Channel::query()->withoutGlobalScopes()->select('id')))
            ->with(['message.channel', 'user'])
            ->orderBy('remind_at')
            ->limit(200)
            ->get();

        foreach ($due as $reminder) {
            $chat->reminderDue($reminder);
            $reminder->forceFill(['sent_at' => now()])->save();
        }

        if ($due->isNotEmpty()) {
            $this->info("{$due->count()} Erinnerung(en) verschickt.");
        }

        return self::SUCCESS;
    }
}
