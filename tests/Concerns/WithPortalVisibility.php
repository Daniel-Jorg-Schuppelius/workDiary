<?php
/*
 * Created on   : Mon Aug 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WithPortalVisibility.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Concerns;

use App\Enums\CustomerPortal\PortalCapability;
use App\Models\Customer;

/**
 * Portal-Bereichsfreigaben (MVP-511) für Feature-Tests: seit Default-Deny
 * braucht jeder Portal-Test eine ausdrückliche Freigabe des Kunden. Ohne
 * Argumente entspricht der Stand dem Kompatibilitäts-Vollumfang von vor
 * MVP-511 (alle Bereiche, Zeiten inkl. Beschreibung, Scope „alle").
 */
trait WithPortalVisibility {
    /** @param array<int, string>|null $capabilities null = alle Bereiche */
    protected function allowPortal(Customer $customer, ?array $capabilities = null, string $timeDetail = 'entries_with_description', string $timeScope = 'all'): void {
        $customer->forceFill(['portal_settings' => [
            'enabled' => true,
            'capabilities' => $capabilities ?? array_map(
                static fn (PortalCapability $c): string => $c->value,
                PortalCapability::cases(),
            ),
            'time_detail' => $timeDetail,
            'time_scope' => $timeScope,
        ]])->save();
    }
}
