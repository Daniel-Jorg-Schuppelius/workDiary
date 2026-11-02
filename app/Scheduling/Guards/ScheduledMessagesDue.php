<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScheduledMessagesDue.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Scheduling\Guards;

use App\Models\Chat\ScheduledMessage;
use App\Scheduling\Contracts\JobGuard;

/** Geplante Chat-Nachrichten (chat:send-scheduled): nur mit fälliger Nachricht. */
final class ScheduledMessagesDue implements JobGuard {
    public function shouldRun(): bool {
        return ScheduledMessage::query()->where('scheduled_at', '<=', now())->exists();
    }
}
