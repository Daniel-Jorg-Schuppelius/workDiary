<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GuaranteeKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Guarantee;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Bürgschaftsregister (Feature 114, MVP-603). */
enum GuaranteeKind: string implements HasLabel {
    use HasOptions;

    case Performance = 'performance';
    case Warranty = 'warranty';
    case AdvancePayment = 'advance_payment';
    case Defects = 'defects';

    public function label(): string {
        return (string) __('enums.guarantee_kind.' . $this->value);
    }
}
