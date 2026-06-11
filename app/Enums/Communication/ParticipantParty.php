<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ParticipantParty.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Communication;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum ParticipantParty: string implements HasLabel {
    use HasOptions;

    case Internal = 'internal';
    case Customer = 'customer';
    case ThirdParty = 'thirdParty';

    public function label(): string {
        return (string) __('enums.communication.party.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::Internal => 'ghost',
            self::Customer => 'info',
            self::ThirdParty => 'warning',
        };
    }
}
