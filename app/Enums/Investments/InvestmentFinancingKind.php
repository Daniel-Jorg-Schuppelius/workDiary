<?php
/*
 * Created on   : Sat Sep 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvestmentFinancingKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Investments;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Finanzierungsart einer Investitionsvariante (MVP-907). */
enum InvestmentFinancingKind: string implements HasLabel {
    use HasOptions;

    case Purchase = 'purchase';
    case Loan = 'loan';
    case Lease = 'lease';

    public function label(): string {
        return (string) __('investment.financing.kind.' . $this->value);
    }
}
