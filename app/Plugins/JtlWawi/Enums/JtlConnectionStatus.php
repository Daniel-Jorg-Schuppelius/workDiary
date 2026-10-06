<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JtlConnectionStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\JtlWawi\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Stand der JTL-Wawi-Verbindung (MVP-317): Entwurf, Registrierung in der Wawi, dann aktiv oder blockiert. */
enum JtlConnectionStatus: string implements HasLabel {
    use HasOptions;

    case Draft = 'draft';
    case PendingRegistration = 'pending_registration';
    case Active = 'active';
    case Blocked = 'blocked';
    case Disconnected = 'disconnected';

    public function label(): string {
        return (string) __('jtl_wawi::jtl_wawi.status.' . $this->value);
    }
}
