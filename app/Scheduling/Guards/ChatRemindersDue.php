<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ChatRemindersDue.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Scheduling\Guards;

use App\Models\Chat\Reminder;
use App\Scheduling\Contracts\JobGuard;

/** Chat-Erinnerungen (chat:send-reminders): nur mit fälliger, ungesendeter Erinnerung. */
final class ChatRemindersDue implements JobGuard {
    public function shouldRun(): bool {
        return Reminder::query()->whereNull('sent_at')->where('remind_at', '<=', now())->exists();
    }
}
