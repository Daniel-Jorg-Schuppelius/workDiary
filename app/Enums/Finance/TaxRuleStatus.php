<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TaxRuleStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Finance;

use App\Enums\Contracts\HasLabel;

/** Stand einer Steuerregel (Phase 23, MVP-238): Regeln werden stillgelegt, nie gelöscht. */
enum TaxRuleStatus: string implements HasLabel {
    case Active = 'active';

    /** Im Schema vorgesehen, heute von keinem Code geschrieben. */
    case Draft = 'draft';
    case Retired = 'retired';

    public function label(): string {
        return (string) __('values.' . $this->value);
    }
}
