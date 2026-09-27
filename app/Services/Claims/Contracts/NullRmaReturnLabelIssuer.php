<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NullRmaReturnLabelIssuer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Claims\Contracts;

use App\Models\Claims\ClaimRmaReturn;
use App\Models\Platform\{Organization, User};
use App\Models\Shipping\Shipment;
use App\Modules\ModuleUnavailableException;

final class NullRmaReturnLabelIssuer implements RmaReturnLabelIssuer {
    public function carriers(Organization $organization): array {
        return [];
    }

    public function issue(ClaimRmaReturn $rma, User $actor, string $carrier, int $weightGrams): Shipment {
        throw ModuleUnavailableException::for('module.versand');
    }
}
