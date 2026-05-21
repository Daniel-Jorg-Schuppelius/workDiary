<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReminderChannel.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Event;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Werte korrespondieren 1:1 mit Laravel-Notification-Channels
 * (mail, webpush, database), damit sie direkt in via() verwendet werden
 * können.
 */
enum ReminderChannel: string implements HasLabel {
    use HasOptions;

    case Mail = 'mail';
    case WebPush = 'webpush';
    case Database = 'database';

    public function label(): string {
        return (string) __('enums.event.reminder.channel.' . $this->value);
    }
}
