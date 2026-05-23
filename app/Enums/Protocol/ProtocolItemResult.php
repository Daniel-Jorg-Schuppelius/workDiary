<?php
/*
 * Created on   : Sun May 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolItemResult.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Protocol;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum ProtocolItemResult: string implements HasLabel {
    use HasOptions;

    case Ok = 'ok';
    case NotOk = 'notok';
    case NotApplicable = 'n_a';
    case Open = 'open';

    public function label(): string {
        return (string) __('enums.protocol.item-result.' . $this->value);
    }
}
