<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EventRemindersDue.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Scheduling\Guards;

use App\Models\Calendar\EventReminder;
use App\Scheduling\Contracts\JobGuard;

/** Terminerinnerungen (events:dispatch-reminders): nur mit fälliger Erinnerung. */
final class EventRemindersDue implements JobGuard {
    public function shouldRun(): bool {
        return EventReminder::query()->due(now())->exists();
    }
}
