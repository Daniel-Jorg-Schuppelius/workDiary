<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatevConnectionStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\DatevOnline\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Stand der DATEV-Verbindung einer Organisation (MVP-122). */
enum DatevConnectionStatus: string implements HasLabel {
    use HasOptions;

    case Active = 'active';
    case Disconnected = 'disconnected';

    public function label(): string {
        return (string) __('datev-online::datev.connection_status.' . $this->value);
    }
}
