<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RmaReturnLabelIssuer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Claims\Contracts;

use App\Models\Claims\ClaimRmaReturn;
use App\Models\Platform\{Organization, User};
use App\Models\Shipping\Shipment;

/** Retourenlabel für eine Rücksendung (MVP-917), gebunden vom Versandmodul. */
interface RmaReturnLabelIssuer {
    /**
     * Aktive Carrier-Anbindungen der Organisation (Kennung → Bezeichnung).
     *
     * @return array<string, string>
     */
    public function carriers(Organization $organization): array;

    /** Erzeugt das Label beim Carrier: Absender der Kunde der Reklamation, Empfänger die Organisation. */
    public function issue(ClaimRmaReturn $rma, User $actor, string $carrier, int $weightGrams): Shipment;
}
