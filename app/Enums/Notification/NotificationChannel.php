<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NotificationChannel.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Notification;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Zustellkanäle für Benachrichtigungen (MVP-018). Webhook/Kalender/SMS sind
 * bewusst out of scope; Push läuft über die bestehende WebPush-Infrastruktur.
 */
enum NotificationChannel: string implements HasLabel {
    use HasOptions;

    case InApp = 'inApp';
    case Mail = 'mail';
    case Push = 'push';

    public function label(): string {
        return (string) __('enums.notification.channel.' . $this->value);
    }
}
