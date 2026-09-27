<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvestmentOrigin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Investments;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Herkunft einer Investitionsakte (MVP-936). */
enum InvestmentOrigin: string implements HasLabel {
    use HasOptions;

    case Staff = 'staff';
    case Public = 'public';

    public function label(): string {
        return (string) __('investment.proposal.origin.' . $this->value);
    }
}
