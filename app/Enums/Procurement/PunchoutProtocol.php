<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PunchoutProtocol.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Procurement;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Absprungprotokoll einer Bezugsquelle in den Lieferanten-Shop (MVP-096, MVP-1071). */
enum PunchoutProtocol: string implements HasLabel {
    use HasOptions;

    case Oci = 'oci';
    case Ids = 'ids';

    public function label(): string {
        return __('procurement.oci.protocol.' . $this->value);
    }
}
