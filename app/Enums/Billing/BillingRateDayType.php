<?php
/*
 * Created on   : Thu Jul 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillingRateDayType.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Billing;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Tagtyp eines Sonderkonditions-Satzes (Feature 098). Was als „Wochenende"
 * zählt, definiert workdays_per_week am Agreement (6 ⇒ nur Sonntag, 5 ⇒ Sa+So).
 */
enum BillingRateDayType: string implements HasLabel {
    use HasOptions;

    case Weekday = 'weekday';
    case Weekend = 'weekend';

    public function label(): string {
        return (string) __('enums.billing.rate-day-type.' . $this->value);
    }
}
