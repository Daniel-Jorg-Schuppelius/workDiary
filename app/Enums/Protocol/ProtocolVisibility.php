<?php
/*
 * Created on   : Sun May 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolVisibility.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Protocol;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum ProtocolVisibility: string implements HasLabel {
    use HasOptions;

    case Internal = 'internal';
    case Customer = 'customer';

    public function label(): string {
        return (string) __('enums.protocol.visibility.' . $this->value);
    }
}
