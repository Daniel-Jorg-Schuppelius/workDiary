<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FixedAssetDisposalKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Finance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Art des Anlagenabgangs (Feature 133, MVP-891). */
enum FixedAssetDisposalKind: string implements HasLabel {
    use HasOptions;

    case Sale = 'sale';
    case Scrap = 'scrap';

    public function label(): string {
        return (string) __('enums.finance.fixed-asset-disposal-kind.' . $this->value);
    }
}
