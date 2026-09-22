<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubNotificationStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Club;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Zustellstand einer Vereinsnachricht (MVP-845): zugestellt oder fehlgeschlagen — kein Gelesen-Status. */
enum ClubNotificationStatus: string implements HasLabel {
    use HasOptions;

    case Sent = 'sent';
    case Failed = 'failed';

    public function label(): string {
        return (string) __('enums.club.notification-status.' . $this->value);
    }

    public function tone(): string {
        return $this === self::Sent ? 'success' : 'error';
    }
}
