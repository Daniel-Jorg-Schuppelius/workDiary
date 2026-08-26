<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SmsDeliveryStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Notification;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Zustellstatus einer SMS (Feature 147, MVP-730) — geführt je Empfänger im
 * `notification_dispatch_log`.
 *
 * `Sent` heißt „vom Gateway angenommen", nicht „beim Empfänger": echte
 * Zustellquittungen (DLR) kommen erst über den optionalen Status-Webhook des
 * Anbieters und heben den Eintrag dann auf `Delivered`. `Blocked` ist kein
 * Fehler des Anbieters, sondern eine eigene Entscheidung (Budgetdeckel,
 * fehlendes Opt-in) — sie kostet nichts und erzeugt keinen Versuch.
 */
enum SmsDeliveryStatus: string implements HasLabel {
    use HasOptions;

    case Sent = 'sent';

    case Delivered = 'delivered';

    case Failed = 'failed';

    case Blocked = 'blocked';

    public function label(): string {
        return (string) __('enums.notification.sms_status.' . $this->value);
    }

    /** Hat der Versuch tatsächlich Geld gekostet (zählt gegen das Monatsbudget)? */
    public function isBillable(): bool {
        return $this === self::Sent || $this === self::Delivered;
    }
}
