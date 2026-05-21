<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DispatchEventRemindersCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands\Event;

use App\Services\Event\ReminderService;
use Illuminate\Console\Command;

class DispatchEventRemindersCommand extends Command {
    protected $signature = 'events:dispatch-reminders';

    protected $description = 'Versendet fällige Event-Erinnerungen (Mail/DB/WebPush).';

    public function handle(ReminderService $reminders): int {
        $sent = $reminders->dispatchDue();
        $this->info("Dispatched {$sent} reminder(s).");

        return self::SUCCESS;
    }
}
