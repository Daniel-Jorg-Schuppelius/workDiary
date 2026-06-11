<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CommunicationDirection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Communication;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum CommunicationDirection: string implements HasLabel {
    use HasOptions;

    case Inbound = 'inbound';
    case Outbound = 'outbound';
    case Internal = 'internal';

    public function label(): string {
        return (string) __('enums.communication.direction.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::Inbound => 'info',
            self::Outbound => 'success',
            self::Internal => 'ghost',
        };
    }

    /** Material-Symbols-Icon für Listen/Panels. */
    public function icon(): string {
        return match ($this) {
            self::Inbound => 'call_received',
            self::Outbound => 'call_made',
            self::Internal => 'sync_alt',
        };
    }
}
