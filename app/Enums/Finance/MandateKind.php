<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MandateKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Finance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** SEPA-Zahlungsausgang (Feature 120, MVP-609). */
enum MandateKind: string implements HasLabel {
    use HasOptions;

    case OneOff = 'one_off';
    case Recurring = 'recurring';

    public function label(): string {
        return (string) __('enums.sepa_mandate_kind.' . $this->value);
    }
}
