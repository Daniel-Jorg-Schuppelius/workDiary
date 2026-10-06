<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MeterBillingAgreementStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Metering;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Status einer Abrechnungsvereinbarung (Feature 116, MVP-605). */
enum MeterBillingAgreementStatus: string implements HasLabel {
    use HasOptions;

    case Active = 'active';
    case Paused = 'paused';
    case Ended = 'ended';

    public function label(): string {
        return (string) __('metering.status.' . $this->value);
    }
}
