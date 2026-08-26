<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DepreciationMethod.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Finance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * AfA-Methode einer Anlage (Feature 133, MVP-698).
 *
 * Im MVP nur linear (§ 7 Abs. 1 EStG, monatsgenau im Anschaffungs- und
 * Abgangsjahr). Degressiv (§ 7 Abs. 2 EStG, befristet), Leistungs-AfA und
 * Sonderabschreibungen (§ 7g EStG) sind bewusst nicht enthalten — sie
 * brauchen Wahlrechts- und Fristlogik, die der DepreciationCalculator
 * erst mit einem Fachentscheid bekommen soll.
 */
enum DepreciationMethod: string implements HasLabel {
    use HasOptions;

    case Linear = 'linear';

    public function label(): string {
        return (string) __('enums.finance.depreciation-method.' . $this->value);
    }
}
