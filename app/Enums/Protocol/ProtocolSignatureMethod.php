<?php
/*
 * Created on   : Sun May 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolSignatureMethod.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Protocol;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum ProtocolSignatureMethod: string implements HasLabel {
    use HasOptions;

    case Onscreen = 'onscreen';
    case Portal = 'portal';
    case EmailLink = 'emailLink';
    case Paper = 'paper';

    public function label(): string {
        return (string) __('enums.protocol.signature-method.' . $this->value);
    }
}
