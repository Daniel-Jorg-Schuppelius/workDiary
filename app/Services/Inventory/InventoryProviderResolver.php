<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InventoryProviderResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Contracts\Inventory\InventoryProvider;
use App\Enums\Inventory\InventoryMode;
use App\Models\Organization;
use RuntimeException;

/**
 * Löst die Bestandsführerschaft und den aktiven Provider je Organisation auf
 * (Feature 048, MVP-066) – analog {@see \App\Services\Finance\BillingModeResolver}.
 * Kaskade: organizations.settings['inventory_mode'] → Fallback `local`. Externe
 * Provider (JTL-Wawi) folgen als Plugin (MVP-073); bis dahin ist nur der lokale
 * Provider verfügbar.
 */
class InventoryProviderResolver {
    public function __construct(private readonly LocalInventoryProvider $local) {}

    public function modeFor(Organization $organization): InventoryMode {
        $setting = data_get($organization->settings, 'inventory_mode');
        if (is_string($setting) && $setting !== '') {
            $mode = InventoryMode::tryFrom($setting);
            if ($mode instanceof InventoryMode) {
                return $mode;
            }
        }

        return InventoryMode::Local;
    }

    public function providerFor(Organization $organization): InventoryProvider {
        return match ($this->modeFor($organization)) {
            InventoryMode::Local => $this->local,
            InventoryMode::External, InventoryMode::ReadOnly => throw new RuntimeException(
                'Für externe Bestandsführung ist noch kein Provider registriert (Plugin folgt in MVP-073).'
            ),
        };
    }
}
