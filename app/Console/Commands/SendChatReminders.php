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

use App\Models\Chat\Reminder;
use App\Services\PushNotifier;
use Illuminate\Console\Command;

/**
 * Verschickt fällige Chat-Erinnerungen (remind_at erreicht) per Web-Push
 * und markiert sie als versendet. Läuft minütlich über den Scheduler.
 */
class SendChatReminders extends Command {
    protected $signature = 'chat:send-reminders';

    protected $description = 'Verschickt fällige Chat-Erinnerungen per Web-Push.';

    public function handle(PushNotifier $push): int {
        $due = Reminder::query()
            ->whereNull('sent_at')
            ->where('remind_at', '<=', now())
            ->with(['message.channel', 'user'])
            ->orderBy('remind_at')
            ->limit(200)
            ->get();

        foreach ($due as $reminder) {
            $push->chatReminder($reminder);
            $reminder->forceFill(['sent_at' => now()])->save();
        }

        if ($due->isNotEmpty()) {
            $this->info("{$due->count()} Erinnerung(en) verschickt.");
        }

        return self::SUCCESS;
    }
}
