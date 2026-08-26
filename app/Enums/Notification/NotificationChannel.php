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
 * Zustellkanäle für Benachrichtigungen (MVP-018). InApp/Mail/Push sind
 * empfängerbezogen; Teams/Mattermost (Feature 056, MVP-119) sind org-weite
 * ausgehende Chat-Webhook-Kanäle (eine Kanal-URL je Organisation), die über
 * dieselbe Ereignis→Kanal-Matrix ausgewählt werden. Calendar (MVP-331,
 * Bauturbo A11) publiziert terminartige Ereignisse (Payload mit `due_at`)
 * idempotent als Kalendereintrag in die verbundenen Kalender der Organisation
 * (CalDAV/Microsoft 365/Google — A8-Publish-Infrastruktur).
 */
enum NotificationChannel: string implements HasLabel {
    use HasOptions;

    case InApp = 'inApp';
    case Mail = 'mail';
    case Push = 'push';
    case Teams = 'teams';
    case Mattermost = 'mattermost';
    case Calendar = 'calendar';
    /**
     * SMS-Kurznachricht (Feature 147, MVP-730) — empfängerbezogen, aber nur
     * für als kritisch markierte Ereignisse ({@see NotificationEvent::supportsSms()})
     * und nur an Mitarbeitende mit bestätigtem Opt-in. Versand über die
     * SMS-Gateway-Plugins (seven.io/sipgate) der Organisation.
     */
    case Sms = 'sms';

    public function label(): string {
        return (string) __('enums.notification.channel.' . $this->value);
    }

    /** Org-weite ausgehende Chat-Webhook-Kanäle (nicht empfängerbezogen). */
    public function isChatChannel(): bool {
        return $this === self::Teams || $this === self::Mattermost;
    }

    /**
     * Kanäle, die je Ereignis freigeschaltet sein müssen: SMS kostet Geld und
     * verlässt die Plattform (Rufnummer beim Gateway), darum ist er nirgends
     * Default und nur an kritischen Ereignissen überhaupt wählbar.
     */
    public function isRestrictedToCriticalEvents(): bool {
        return $this === self::Sms;
    }
}
